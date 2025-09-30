<?php
// inc/helpers.php
declare(strict_types=1);

function sanitize(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** 업로드 파일명 보안 처리 */
function secure_filename(string $name): string {
  $name = preg_replace('/[^\w\-.]+/u', '_', $name);
  $name = trim($name, '._');
  if ($name === '') $name = 'file';
  return $name;
}

/** JSON 응답 */
function json_response(bool $ok, $data = null, int $http = 200): void {
  http_response_code($http);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok'      => $ok,
    'data'    => $ok ? $data : null,
    'error'   => $ok ? null : ($data ?? 'error'),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/** 로깅 */
function app_log(string $msg): void {
  if (!is_dir(LOG_DIR)) @mkdir(LOG_DIR, 0775, true);
  @file_put_contents(APP_LOG, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}
function gemini_log(string $msg): void {
  if (!is_dir(LOG_DIR)) @mkdir(LOG_DIR, 0775, true);
  @file_put_contents(GEMINI_LOG, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

/** 간단한 입력 유효성 */
function require_post(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
  }
}