<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/_bootstrap.php';

require_admin(); // 관리자 화면에서만 사용
$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($jobId <= 0) json_response(false, 'id 필요', 400);

$stmt = $pdo->prepare("SELECT id, media_kit_id, status, progress, error_message, created_at, started_at, finished_at
                       FROM pdf_process_jobs WHERE id=:id");
$stmt->execute([':id'=>$jobId]);
$row = $stmt->fetch();
if (!$row) json_response(false, 'not found', 404);

json_response(true, $row, 200);