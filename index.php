<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
}
?>
<?php
require_once __DIR__ . '/inc/header.php';
?>

<div class="w-full max-w-md bg-white p-8 rounded shadow">
  <h2 class="text-2xl font-bold text-center mb-6">시작하기</h2>
  <div class="flex flex-col space-y-4">
    <a href="/auth/login.php" 
       class="block w-full text-center bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition">
       로그인
    </a>
    <a href="/auth/signup.php" 
       class="block w-full text-center bg-gray-200 text-gray-800 py-2 px-4 rounded hover:bg-gray-300 transition">
       회원가입
    </a>
  </div>
</div>

<?php
require_once __DIR__ . '/inc/footer.php';
?>