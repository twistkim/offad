<?php
require_once __DIR__ . '/../inc/_bootstrap.php';

// 세션 파기
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params['path'], $params['domain'],
    $params['secure'], $params['httponly']
  );
}
session_destroy();

// 홈으로
header('Location: /');
exit;