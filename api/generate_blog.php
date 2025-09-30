<?php
declare(strict_types=1);

/**
 * 사용자 전용: 선택한 승인 매체 + 입력 정보로 블로그 원문 생성
 * - POST only (JSON/FORM 모두 허용)
 * - 요청(JSON 예상):
 *   {
 *     company_name: string,
 *     contact_name: string,
 *     contact_phone: string?,
 *     keywords: string,
 *     tone: "formal"|"friendly"|"persuasive"|"neutral",
 *     length_hint: number,        // 예: 3000
 *     media_ids: number[]         // processed_media.id 배열
 *   }
 * - 성공: { ok:true, data:{ blog_request_id, content } }
 */

require_once __DIR__ . '/../inc/_bootstrap.php';

require_login();
require_post();

// CSRF (헤더 또는 폼 히든)
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$formToken   = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $headerToken ?: $formToken)) {
  json_response(false, 'Invalid CSRF token', 400);
}

// 입력 파싱(JSON 우선)
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $in  = json_decode($raw ?: '[]', true) ?: [];
} else {
  // 폼 전송 지원
  $in = $_POST;
  // media_ids[] 형태 처리
  if (isset($_POST['media_ids']) && is_array($_POST['media_ids'])) {
    $in['media_ids'] = array_map('intval', $_POST['media_ids']);
  }
}

// 필수값
$company  = trim((string)($in['company_name'] ?? ''));
$contact  = trim((string)($in['contact_name'] ?? ''));
$keywords = trim((string)($in['keywords'] ?? ''));
$mediaIds = $in['media_ids'] ?? [];

if ($company === '' || $contact === '' || $keywords === '' || !is_array($mediaIds) || count($mediaIds) === 0) {
  json_response(false, '회사명, 담당자, 키워드, 매체 선택은 필수입니다.', 400);
}

// 옵션값
$phone   = trim((string)($in['contact_phone'] ?? ''));
$tone    = in_array(($in['tone'] ?? 'formal'), ['formal','friendly','persuasive','neutral'], true)
         ? (string)$in['tone'] : 'formal';
$length  = (int)($in['length_hint'] ?? 3000);
if ($length < 1000) $length = 1000;
if ($length > 8000) $length = 8000;

// 매체 유효성: approved인 media_kits에 속한 processed_media만 허용
// (승인 매체는 누구나 사용할 수 있는 공개 리소스로 가정)
$mediaIds = array_values(array_unique(array_map('intval', $mediaIds)));
$placeholders = implode(',', array_fill(0, count($mediaIds), '?'));

$stmt = $pdo->prepare("
  SELECT pm.id, pm.media_kit_id, pm.name, pm.description, pm.specifications_json,
         pm.target_audience, pm.pricing
  FROM processed_media pm
  JOIN media_kits mk ON mk.id = pm.media_kit_id
  WHERE pm.id IN ($placeholders) AND mk.status = 'approved'
");
$stmt->execute($mediaIds);
$rows = $stmt->fetchAll();

if (!$rows || count($rows) === 0) {
  json_response(false, '선택한 매체를 찾을 수 없거나 승인 상태가 아닙니다.', 400);
}

// 요청 저장 + 매체 연결
$u = current_user();
$userId = (int)$u['id'];

try {
  $pdo->beginTransaction();

  // 1) blog_requests
  $insReq = $pdo->prepare("
    INSERT INTO blog_requests
      (user_id, company_name, contact_name, contact_phone, keywords, tone, length_hint, model, status, created_at)
    VALUES
      (:uid, :company, :contact, :phone, :keywords, :tone, :len, :model, 'pending', NOW())
  ");
  $insReq->execute([
    ':uid'     => $userId,
    ':company' => $company,
    ':contact' => $contact,
    ':phone'   => $phone ?: null,
    ':keywords'=> $keywords,
    ':tone'    => $tone,
    ':len'     => $length,
    ':model'   => GEMINI_MODEL_GENERATE,
  ]);
  $blogRequestId = (int)$pdo->lastInsertId();

  // 2) blog_request_media (다대다 연결)
  $insRel = $pdo->prepare("
    INSERT INTO blog_request_media (blog_request_id, processed_media_id)
    VALUES (:bid, :pmid)
  ");
  foreach ($rows as $r) {
    $insRel->execute([
      ':bid'  => $blogRequestId,
      ':pmid' => (int)$r['id']
    ]);
  }

  // 커밋은 생성 완료 후 한 번에 — 여기선 생성/저장까지 원트랜잭션으로 유지
  // (단, 외부 API 호출 전 커밋하면 실패시 상태가 어정쩡해질 수 있음)
  // → 외부 호출 실패하면 롤백해서 요청 자체를 없앰 (또는 실패 상태로 남기고 재시도 버튼도 가능)
  // 본 구현은 실패 시 롤백.

  // 3) Gemini 호출 준비 데이터
  $userInfo = [
    'company_name'  => $company,
    'contact_name'  => $contact,
    'contact_phone' => $phone
  ];

  // mediaList: 모델에게 주기 좋게 정리
  $mediaList = [];
  foreach ($rows as $r) {
    $specs = [];
    if (!empty($r['specifications_json'])) {
      $tmp = json_decode((string)$r['specifications_json'], true);
      if (is_array($tmp)) $specs = $tmp;
    }
    $mediaList[] = [
      'id'              => (int)$r['id'],
      'name'            => (string)$r['name'],
      'description'     => (string)($r['description'] ?? ''),
      'specifications'  => $specs,
      'target_audience' => (string)($r['target_audience'] ?? ''),
      'pricing'         => (string)($r['pricing'] ?? ''),
    ];
  }

  // 4) Gemini 호출
  $client  = new GeminiClient(GEMINI_API_KEY);
  $content = $client->generateBlog($userInfo, $mediaList, $keywords, $tone, $length);
  $content = trim((string)$content);

  if ($content === '') {
    // 실패 처리
    // 요청 상태를 failed로 남기려면: 상태 업데이트 후 커밋
    $pdo->prepare("UPDATE blog_requests SET status='failed' WHERE id=:id")->execute([':id'=>$blogRequestId]);
    $pdo->commit();
    json_response(false, '생성된 컨텐츠가 비어 있습니다.', 500);
  }

  // 5) blog_outputs 저장
  $insOut = $pdo->prepare("
    INSERT INTO blog_outputs (blog_request_id, content, tokens_input, tokens_output, created_at)
    VALUES (:bid, :content, NULL, NULL, NOW())
  ");
  $insOut->execute([
    ':bid'     => $blogRequestId,
    ':content' => $content
  ]);

  // 6) 상태 완료
  $pdo->prepare("UPDATE blog_requests SET status='completed' WHERE id=:id")
      ->execute([':id' => $blogRequestId]);

  $pdo->commit();

  json_response(true, [
    'blog_request_id' => $blogRequestId,
    'content'         => $content
  ], 200);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  app_log('[generate_blog] ' . $e->getMessage());
  json_response(false, '생성 중 오류가 발생했습니다.', 500);
}