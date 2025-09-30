<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/_bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) json_response(false, 'id 필요', 400);

$uid = (int)current_user()['id'];
// 내 소유 확인
$st = $pdo->prepare("SELECT id FROM blog_requests WHERE id=:id AND user_id=:uid");
$st->execute([':id'=>$id, ':uid'=>$uid]);
if (!$st->fetch()) json_response(false, 'not found', 404);

// 본문
$st = $pdo->prepare("SELECT content FROM blog_outputs WHERE blog_request_id=:id ORDER BY id DESC LIMIT 1");
$st->execute([':id'=>$id]);
$row = $st->fetch();
$content = (string)($row['content'] ?? '');

json_response(true, ['id'=>$id, 'content'=>$content], 200);