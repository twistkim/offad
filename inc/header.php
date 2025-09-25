<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userName = $_SESSION['user_name'] ?? null;
$userCompany = $_SESSION['user_company'] ?? null;
?>
<!doctype html>
<html lang="ko" class="h-full bg-gray-950">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $pageTitle ?? 'AI 옥외광고 블로그 생성기' ?></title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full text-gray-100 flex flex-col min-h-screen">

<!-- 헤더 -->
<header class="bg-gray-900 border-b border-gray-800">
  <div class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">
    <a href="index.php" class="text-xl font-bold text-indigo-400">
      OffAd 블로그 생성기
    </a>
    <nav class="flex items-center space-x-6 text-sm">
      <?php if ($userName): ?>
        <span class="text-gray-300">
          <?= htmlspecialchars($userName) ?> (<?= htmlspecialchars($userCompany) ?>)
        </span>
        <a href="posts.php" class="hover:text-indigo-400">내 글목록</a>
        <a href="logout.php" class="hover:text-red-400">로그아웃</a>
      <?php else: ?>
        <a href="login.php" class="hover:text-indigo-400">로그인</a>
        <a href="register.php" class="hover:text-indigo-400">회원가입</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="flex-1 max-w-6xl mx-auto w-full px-4 py-8">