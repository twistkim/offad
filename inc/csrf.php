<?php
// inc/csrf.php
declare(strict_types=1);

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

function csrf_field_html(): string {
  return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify_or_die(?string $token): void {
  $valid = hash_equals($_SESSION['csrf'] ?? '', $token ?? '');
  if (!$valid) {
    http_response_code(400);
    echo 'Invalid CSRF token';
    exit;
  }
}

/** AJAX 헤더용(X-CSRF-Token) 검증 */
function csrf_verify_header_or_die(): void {
  $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  csrf_verify_or_die($token);
}