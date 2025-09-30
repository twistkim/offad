<?php
// inc/_bootstrap.php
declare(strict_types=1);

// 1) 세션 & 기본 설정
if (session_status() === PHP_SESSION_NONE) {
  // 세션 보안 옵션 (공유호스팅 호환)
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

date_default_timezone_set('Asia/Seoul');

// 개발 중 에러 보기 (운영에서는 주석 처리 권장)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 2) 필수 include
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/secrets.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/GeminiClient.php';

// 3) 전역 PDO 준비
/** @var PDO $pdo */
$pdo = db_connect();

// 4) CSRF 토큰 기본 보장
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}