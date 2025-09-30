<?php
declare(strict_types=1);

/**
 * 로그인 사용자 전용 PDF 업로드 API
 * - 멀티파트 POST만 허용
 * - 입력 파일 필드명: "pdf"
 * - 성공 시 { ok: true, data: { media_kit_id, original_filename } }
 */

require_once __DIR__ . '/../inc/_bootstrap.php';

require_login();
require_post();

// CSRF (헤더 또는 폼 히든 둘 중 하나라도 통과)
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$formToken   = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $headerToken ?: $formToken)) {
  json_response(false, 'Invalid CSRF token', 400);
}

// 업로드 기본 체크
if (empty($_FILES['pdf']) || !is_array($_FILES['pdf'])) {
  json_response(false, '업로드된 파일이 없습니다. (필드명: pdf)', 400);
}

$file   = $_FILES['pdf'];
$err    = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
$tmp    = $file['tmp_name'] ?? '';
$orig   = $file['name'] ?? 'file.pdf';
$size   = (int)($file['size'] ?? 0);

if ($err !== UPLOAD_ERR_OK) {
  $msg = match ($err) {
    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '파일 용량이 제한을 초과했습니다.',
    UPLOAD_ERR_PARTIAL => '파일이 부분적으로만 업로드되었습니다.',
    UPLOAD_ERR_NO_FILE => '파일이 업로드되지 않았습니다.',
    UPLOAD_ERR_NO_TMP_DIR => '서버 임시 폴더가 없습니다.',
    UPLOAD_ERR_CANT_WRITE => '서버에 파일을 쓸 수 없습니다.',
    UPLOAD_ERR_EXTENSION => '확장자 제한으로 업로드에 실패했습니다.',
    default => '알 수 없는 업로드 오류입니다.',
  };
  json_response(false, $msg, 400);
}

if (!is_uploaded_file($tmp)) {
  json_response(false, '유효하지 않은 업로드입니다.', 400);
}

// 용량 제한
if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
  json_response(false, '파일 용량 제한을 초과했습니다. (최대 ' . number_format(MAX_UPLOAD_BYTES) . ' bytes)', 400);
}

// MIME 검사 (서버 기준)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmp) ?: 'application/octet-stream';

// 허용 MIME
$allowed = json_decode(ALLOWED_MIME, true);
if (!is_array($allowed) || !$allowed) $allowed = ['application/pdf'];

if (!in_array($mime, $allowed, true)) {
  json_response(false, '허용되지 않은 파일 형식입니다. (감지된 형식: ' . $mime . ')', 400);
}

// 저장 디렉터리 보장
if (!is_dir(UPLOAD_DIR)) {
  @mkdir(UPLOAD_DIR, 0775, true);
}

// 안전한 저장 파일명 생성
$origSafe   = secure_filename($orig);
$ext        = strtolower(pathinfo($origSafe, PATHINFO_EXTENSION));
if ($ext !== 'pdf') $ext = 'pdf';

$rand = bin2hex(random_bytes(6));
$stored = date('Ymd_His') . '_' . $rand . '.' . $ext;
$dest   = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $stored;

// 이동
if (!move_uploaded_file($tmp, $dest)) {
  json_response(false, '파일을 저장하지 못했습니다.', 500);
}

// DB insert (media_kits: pending)
try {
  $u = current_user();
  $stmt = $pdo->prepare("
    INSERT INTO media_kits
      (user_id, original_filename, stored_filename, mime_type, file_size, status, uploaded_at)
    VALUES
      (:user_id, :original_filename, :stored_filename, :mime_type, :file_size, 'pending', NOW())
  ");
  $stmt->execute([
    ':user_id'          => (int)$u['id'],
    ':original_filename'=> $origSafe,
    ':stored_filename'  => $stored,
    ':mime_type'        => $mime,
    ':file_size'        => $size,
  ]);
  $id = (int)$pdo->lastInsertId();

  json_response(true, [
    'media_kit_id'      => $id,
    'original_filename' => $origSafe,
    'stored_filename'   => $stored,
    'mime_type'         => $mime,
    'file_size'         => $size,
  ], 200);

} catch (Throwable $e) {
  app_log('[upload_media] DB insert fail: ' . $e->getMessage());
  // 실패 시 파일 롤백(선택)
  @unlink($dest);
  json_response(false, '서버 내부 오류로 업로드에 실패했습니다.', 500);
}