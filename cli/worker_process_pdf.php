<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/_bootstrap.php';

// 여러 인스턴스 동시 실행 방지 (MySQL GET_LOCK)
$lockKey = 'worker_process_pdf_lock';
$got = $pdo->query("SELECT GET_LOCK('$lockKey', 0)")->fetchColumn();
if ((int)$got !== 1) {
  exit; // 이미 다른 워커가 돌고 있음
}

try {
  // 잡 하나 집어서 실행 (running 아닌 것 중 queued 우선)
  $pdo->beginTransaction();

  // FOR UPDATE로 잠그기 (5.7 호환: SKIP LOCKED 없으면 LIMIT 1 + 상태 갱신으로 레이스 방지)
  $job = $pdo->query("
    SELECT id, media_kit_id FROM pdf_process_jobs
    WHERE status='queued'
    ORDER BY id ASC
    LIMIT 1
    FOR UPDATE
  ")->fetch();

  if (!$job) {
    $pdo->commit();
    exit; // 처리할 일 없음
  }

  $pdo->prepare("UPDATE pdf_process_jobs SET status='running', started_at=NOW(), attempts=attempts+1 WHERE id=:id")
      ->execute([':id'=>$job['id']]);
  $pdo->commit();

  // 실제 처리
  $jid = (int)$job['id'];
  $mkid = (int)$job['media_kit_id'];

  try {
    // 진행률 업데이트 도우미
    $setProgress = function(int $p) use ($pdo, $jid) {
      $pdo->prepare("UPDATE pdf_process_jobs SET progress=:p WHERE id=:id")
          ->execute([':p'=>$p, ':id'=>$jid]);
    };

    $setProgress(5);

    // 파일 경로
    $mkStmt = $pdo->prepare("SELECT stored_filename FROM media_kits WHERE id=:id");
    $mkStmt->execute([':id'=>$mkid]);
    $stored = $mkStmt->fetchColumn();
    if (!$stored) throw new RuntimeException('media_kit not found');

    $pdfPath = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $stored;
    if (!is_file($pdfPath)) throw new RuntimeException('pdf file missing');

    $setProgress(10);

    // Gemini 호출
    $client = new GeminiClient(GEMINI_API_KEY);
    $items = $client->extractFromPdf($pdfPath);

    $setProgress(60);

    // 결과 저장 (process_pdf.php와 동일 로직)
    $pdo->beginTransaction();
    $ins = $pdo->prepare("
      INSERT INTO processed_media
        (media_kit_id, name, description, specifications_json, target_audience, pricing, raw_json, created_at)
      VALUES
        (:mkid, :name, :desc, :specs, :aud, :price, :raw, NOW())
    ");

    $inserted = 0;
    foreach ($items as $i) {
      $name = trim((string)($i['name'] ?? ''));
      if ($name === '') continue;
      $desc  = trim((string)($i['description'] ?? ''));
      $aud   = trim((string)($i['target_audience'] ?? ($i['targetAudience'] ?? '')));
      $price = trim((string)($i['pricing'] ?? ''));
      $specs = $i['specifications'] ?? ($i['specs'] ?? []);
      if (is_string($specs)) {
        $specs = array_values(array_filter(array_map('trim', preg_split('/\r?\n|\u2022|\-/u', $specs))));
      }
      if (!is_array($specs)) $specs = [];
      $ins->execute([
        ':mkid'=>$mkid,
        ':name'=>$name,
        ':desc'=>$desc ?: null,
        ':specs'=>json_encode($specs, JSON_UNESCAPED_UNICODE),
        ':aud'=>$aud ?: null,
        ':price'=>$price ?: null,
        ':raw'=>json_encode($i, JSON_UNESCAPED_UNICODE),
      ]);
      $inserted++;
    }

    $pdo->prepare("UPDATE media_kits SET status='approved', processed_at=NOW() WHERE id=:id")
        ->execute([':id'=>$mkid]);

    $pdo->commit();

    $setProgress(100);
    $pdo->prepare("UPDATE pdf_process_jobs SET status='done', finished_at=NOW() WHERE id=:id")
        ->execute([':id'=>$jid]);

  } catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    app_log('[worker] fail job='.$jid.' err='.$e->getMessage());
    $pdo->prepare("UPDATE pdf_process_jobs SET status='failed', error_message=:e, finished_at=NOW() WHERE id=:id")
        ->execute([':id'=>$jid, ':e'=>$e->getMessage()]);
  }

} finally {
  $pdo->query("SELECT RELEASE_LOCK('$lockKey')");
}