<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/_bootstrap.php';

require_admin();
require_post();

$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$formToken   = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $headerToken ?: $formToken)) {
  json_response(false, 'Invalid CSRF token', 400);
}

$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
$in = (stripos($ctype, 'application/json') !== false)
  ? (json_decode(file_get_contents('php://input') ?: '[]', true) ?: [])
  : $_POST;

$mediaKitId = (int)($in['media_kit_id'] ?? 0);
if ($mediaKitId <= 0) json_response(false, 'media_kit_id 필요', 400);

// 존재/권한/상태 체크 (승인된 것만 분석 허용해도 되고, pending도 허용 가능)
$mk = $pdo->prepare("SELECT id, stored_filename FROM media_kits WHERE id=:id LIMIT 1");
$mk->execute([':id'=>$mediaKitId]);
if (!$mk->fetch()) json_response(false, '대상을 찾을 수 없습니다.', 404);

// 큐잉
$stmt = $pdo->prepare("INSERT INTO pdf_process_jobs (media_kit_id) VALUES (:id)");
$stmt->execute([':id'=>$mediaKitId]);
$jobId = (int)$pdo->lastInsertId();

json_response(true, ['job_id' => $jobId], 200);