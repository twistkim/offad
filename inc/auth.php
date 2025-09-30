<?php
// inc/auth.php
declare(strict_types=1);

/** 현재 로그인 사용자(배열) 또는 null */
function current_user(): ?array {
  return $_SESSION['user'] ?? null;
}

/** 로그인 필수 */
function require_login(): void {
  if (empty($_SESSION['user'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: /auth/login.php?redirect=' . $redirect);
    exit;
  }
}

/** 관리자 필수 */
function require_admin(): void {
  require_login();
  if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo '관리자 전용 페이지입니다.';
    exit;
  }
}