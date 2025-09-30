<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>AI 광고 블로그 생성기</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
  <!-- 헤더 -->
  <header class="bg-white shadow">
    <div class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">
      <h1 class="text-xl font-bold text-indigo-600">
        <a href="/">AI 광고 블로그 생성기</a>
      </h1>
      <nav>
        <?php if (!empty($_SESSION['user'])): ?>
          <a href="/dashboard.php" class="text-gray-700 hover:text-indigo-600 mr-4">대시보드</a>
          <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <a href="/admin/index.php" class="text-gray-700 hover:text-indigo-600 mr-4">관리자</a>
          <?php endif; ?>
          <a href="/auth/logout.php" class="text-gray-700 hover:text-red-600">로그아웃</a>
        <?php else: ?>
          <a href="/auth/login.php" class="text-gray-700 hover:text-indigo-600 mr-4">로그인</a>
          <a href="/auth/signup.php" class="text-gray-700 hover:text-indigo-600">회원가입</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="flex-1 flex items-center justify-center">