<?php
declare(strict_types=1);

/**
 * 관리자 전용: 매체 상태 변경 API
 * - POST only (JSON 또는 x-www-form-urlencoded 모두 허용)
 * - 파라미터:
 *    - media_kit_id (int, required)
 *    - action: 'approve' | 'reject' (required)
 *    - reason: string (optional, reject일 때 권장)
 * - 성공 시: { ok: true, data: { id, status, reviewed_by, reviewed_at, reject_reason } }
 */

require_once __DIR__ . '/../inc/_bootstrap.php';

require_admin();
require_post();

// CSRF (헤더 or 폼)
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$formToken   = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $headerToken ?: $formToken)) {
  json_response(false, 'Invalid CSRF token', 400);
}

// JSON or FORM 파싱
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
$input = [];
if (stripos($ctype, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $input = json_decode($raw ?: '[]', true) ?: [];
} else {
  $input = $_POST;
}

$mediaKitId = isset($input['media_kit_id']) ? (int)$input['media_kit_id'] : 0;
$action     = isset($input['action']) ? trim((string)$input['action']) : '';
$reason     = isset($input['reason']) ? trim((string)$input['reason']) : '';

if ($mediaKitId <= 0 || !in_array($action, ['approve','reject'], true)) {
  json_response(false, '잘못된 요청입니다.', 400);
}

// 대상 확인
$stmt = $pdo->prepare("SELECT id, status FROM media_kits WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $mediaKitId]);
$row = $stmt->fetch();
if (!$row) {
  json_response(false, '대상을 찾을 수 없습니다.', 404);
}

// 상태 전이 규칙 (원하면 느슨하게 조정)
$from = $row['status'];
if (!in_array($from, ['pending','approved','rejected'], true)) {
  json_response(false, '허용되지 않은 상태입니다.', 400);
}

// 관리자 정보
$adminId = (int)($_SESSION['user']['id'] ?? 0);

try {
  if ($action === 'approve') {
    // 승인
    $q = $pdo->prepare("
      UPDATE media_kits
      SET status = 'approved',
          reviewed_by = :uid,
          reviewed_at = NOW(),
          reject_reason = NULL
      WHERE id = :id
    ");
    $q->execute([':uid'=>$adminId, ':id'=>$mediaKitId]);

    $data = [
      'id'            => $mediaKitId,
      'status'        => 'approved',
      'reviewed_by'   => $adminId,
      'reviewed_at'   => date('Y-m-d H:i:s'),
      'reject_reason' => null,
    ];
    json_response(true, $data, 200);

  } else { // reject
    $q = $pdo->prepare("
      UPDATE media_kits
      SET status = 'rejected',
          reviewed_by = :uid,
          reviewed_at = NOW(),
          reject_reason = :reason
      WHERE id = :id
    ");
    $q->execute([':uid'=>$adminId, ':id'=>$mediaKitId, ':reason'=>$reason ?: null]);

    $data = [
      'id'            => $mediaKitId,
      'status'        => 'rejected',
      'reviewed_by'   => $adminId,
      'reviewed_at'   => date('Y-m-d H:i:s'),
      'reject_reason' => $reason ?: null,
    ];
    json_response(true, $data, 200);
  }

} catch (Throwable $e) {
  app_log('[change_media_status] ' . $e->getMessage());
  json_response(false, '상태 변경 중 오류가 발생했습니다.', 500);
}