<?php
// logout.php
declare(strict_types=1);
session_start();

// 모든 세션 변수 제거
$_SESSION = [];

// 세션 쿠키도 제거(선택)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 세션 자체 파괴
session_destroy();

// 로그인 페이지로 리디렉션 (알림용 파라미터 추가 가능)
header('Location: login.php?logged_out=1');
exit;