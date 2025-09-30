<?php
// inc/config.php
declare(strict_types=1);

// 사이트 기본
define('APP_NAME',   'AI 광고 블로그 생성기');
define('BASE_URL',   '/'); // 루트가 하위 디렉토리라면 '/subdir/' 로 조정

// 업로드
define('UPLOAD_DIR', realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads'));
define('MAX_UPLOAD_BYTES', 20 * 1024 * 1024); // 20MB
define('ALLOWED_MIME', json_encode(['application/pdf'])); // 필요시 확장

// 로그
define('LOG_DIR',    realpath(__DIR__ . '/../logs') ?: (__DIR__ . '/../logs'));
define('APP_LOG',    LOG_DIR . '/app.log');
define('GEMINI_LOG', LOG_DIR . '/gemini.log');

// DB 접속 정보 (카페24에 맞게 변경)
define('DB_HOST', 'localhost');
define('DB_NAME', 'offad');
define('DB_USER', 'offad');
define('DB_PASS', 'RLAghltjr1');
define('DB_CHARSET', 'utf8mb4');

// Gemini 모델 & 엔드포인트(필요 시 변경)
define('GEMINI_MODEL_EXTRACT',  'gemini-2.5-flash');
define('GEMINI_MODEL_GENERATE', 'gemini-2.5-flash');
define('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent');

// 유틸 상수
define('IS_HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');