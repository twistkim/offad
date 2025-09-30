<?php
declare(strict_types=1);

/**
 * 관리자 전용: 승인된 PDF를 Gemini로 분석하여 processed_media에 저장
 * - POST only (JSON/FORM 모두 허용)
 * - 파라미터:
 *    - media_kit_id (int, required)
 *    - reprocess (bool, optional) : true면 기존 processed_media 삭제 후 재분석
 * - 성공: { ok:true, data:{ media_kit_id, inserted, replaced, processed_at } }
 */

require_once __DIR__ . '/../inc/_bootstrap.php';

require_admin();
require_post();

// CSRF 검증(헤더 또는 폼 히든)
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$formToken   = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $headerToken ?: $formToken)) {
  json_response(false, 'Invalid CSRF token', 400);
}

// JSON/FORM 입력 파싱
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $in  = json_decode($raw ?: '[]', true) ?: [];
} else {
  $in = $_POST;
}

$mediaKitId = isset($in['media_kit_id']) ? (int)$in['media_kit_id'] : 0;
$reprocess  = filter_var($in['reprocess'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($mediaKitId <= 0) {
  json_response(false, 'media_kit_id가 필요합니다.', 400);
}

// 대상 PDF 조회
$stmt = $pdo->prepare("
  SELECT id, user_id, original_filename, stored_filename, mime_type, file_size, status, uploaded_at
  FROM media_kits
  WHERE id = :id
  LIMIT 1
");
$stmt->execute([':id' => $mediaKitId]);
$mk = $stmt->fetch();

if (!$mk) {
  json_response(false, '대상을 찾을 수 없습니다.', 404);
}
if (!in_array($mk['status'], ['approved','pending','rejected'], true)) {
  json_response(false, '허용되지 않은 상태입니다.', 400);
}

// 업로드된 PDF 실제 경로 확인
$pdfPath = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $mk['stored_filename'];
if (!is_file($pdfPath)) {
  json_response(false, '업로드 파일을 찾을 수 없습니다.', 404);
}

try {
  // Gemini 호출
  $client = new GeminiClient(GEMINI_API_KEY);
  $items = $client->extractFromPdf($pdfPath); // 배열: [{name, description, specifications[], target_audience, pricing}, ...]

  if (!is_array($items) || !$items) {
    // 빈 결과일 때도 processed_at은 갱신
    $pdo->prepare("UPDATE media_kits SET processed_at = NOW() WHERE id = :id")->execute([':id'=>$mediaKitId]);
    json_response(true, [
      'media_kit_id' => $mediaKitId,
      'inserted'     => 0,
      'replaced'     => false,
      'processed_at' => date('Y-m-d H:i:s'),
      'note'         => '분석 결과가 비어 있습니다.'
    ], 200);
  }

  // 트랜잭션
  $pdo->beginTransaction();

  $replaced = false;
  if ($reprocess) {
    $del = $pdo->prepare("DELETE FROM processed_media WHERE media_kit_id = :id");
    $del->execute([':id' => $mediaKitId]);
    $replaced = true;
  }

  $ins = $pdo->prepare("
    INSERT INTO processed_media
      (media_kit_id, name, description, specifications_json, target_audience, pricing, raw_json, created_at)
    VALUES
      (:mkid, :name, :desc, :specs, :aud, :price, :raw, NOW())
  ");

  $inserted = 0;
  foreach ($items as $i) {
    // 필드 추출 & 보정
    $name  = trim((string)($i['name'] ?? ''));
    if ($name === '') continue; // name은 필수

    $desc  = trim((string)($i['description'] ?? ''));
    $aud   = trim((string)($i['target_audience'] ?? ($i['targetAudience'] ?? '')));
    $price = trim((string)($i['pricing'] ?? ''));
    $specs = $i['specifications'] ?? ($i['specs'] ?? null);
    if (is_string($specs)) {
      // 모델이 문자열로 주는 경우 줄바꿈 기준 분리
      $specs = array_values(array_filter(array_map('trim', preg_split('/\r?\n|\u2022|\-/u', $specs))));
    }
    if (!is_array($specs)) $specs = [];
    $specsJson = json_encode($specs, JSON_UNESCAPED_UNICODE);

    // 원문 전체는 항목 단위로 저장하기 애매하므로, 항목만 JSON으로 저장(필요시 전체 원문은 로그로)
    $raw = json_encode($i, JSON_UNESCAPED_UNICODE);

    $ins->execute([
      ':mkid' => $mediaKitId,
      ':name' => $name,
      ':desc' => $desc ?: null,
      ':specs'=> $specsJson ?: null,
      ':aud'  => $aud ?: null,
      ':price'=> $price ?: null,
      ':raw'  => $raw ?: null,
    ]);
    $inserted++;
  }

  // media_kits 갱신 (승인 상태 유지, 처리 완료 시간 기록)
  $pdo->prepare("UPDATE media_kits SET processed_at = NOW() WHERE id = :id")
      ->execute([':id' => $mediaKitId]);

  $pdo->commit();

  json_response(true, [
    'media_kit_id' => $mediaKitId,
    'inserted'     => $inserted,
    'replaced'     => $replaced,
    'processed_at' => date('Y-m-d H:i:s')
  ], 200);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  gemini_log('[process_pdf] ' . $e->getMessage());
  json_response(false, '분석 중 오류가 발생했습니다.', 500);
}