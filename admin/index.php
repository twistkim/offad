<?php
require_once __DIR__ . '/../inc/_bootstrap.php';
require_once __DIR__ . '/../inc/header.php';

// 통계
$pending = (int)$pdo->query("SELECT COUNT(*) FROM media_kits WHERE status='pending'")->fetchColumn();
$approved = (int)$pdo->query("SELECT COUNT(*) FROM media_kits WHERE status='approved'")->fetchColumn();
$users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// CSRF
$csrf = $_SESSION['csrf'] ?? '';
?>
<meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES,'UTF-8') ?>">

<div class="w-full max-w-6xl mx-auto p-6 space-y-6">
  <h2 class="text-2xl font-bold">관리자 대시보드</h2>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl shadow p-5">
      <div class="text-sm text-gray-500">승인 대기 PDF</div>
      <div class="text-3xl font-bold mt-2"><?= number_format($pending) ?></div>
      <a class="text-indigo-600 text-sm mt-3 inline-block" href="/admin/media_pending.php">목록 보기 →</a>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
      <div class="text-sm text-gray-500">승인 완료 PDF</div>
      <div class="text-3xl font-bold mt-2"><?= number_format($approved) ?></div>
      <a class="text-indigo-600 text-sm mt-3 inline-block" href="/admin/media_approved.php">목록 보기 →</a>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
      <div class="text-sm text-gray-500">가입 사용자</div>
      <div class="text-3xl font-bold mt-2"><?= number_format($users) ?></div>
      <a class="text-indigo-600 text-sm mt-3 inline-block" href="/admin/users.php">사용자 보기 →</a>
    </div>
  </div>
</div>

<script src="/js/admin.js"></script>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>