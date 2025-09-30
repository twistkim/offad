<?php
require_once __DIR__ . '/../inc/_bootstrap.php';
require_once __DIR__ . '/../inc/header.php';

$csrf = $_SESSION['csrf'] ?? '';

$rows = $pdo->query("
  SELECT id, email, role, status, company_name, contact_name, created_at
  FROM users
  ORDER BY created_at DESC
  LIMIT 500
")->fetchAll();
?>
<meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES,'UTF-8') ?>">

<div class="w-full max-w-6xl mx-auto p-6 space-y-6">
  <h2 class="text-2xl font-bold">사용자 목록</h2>
  <div class="bg-white rounded-xl shadow overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left">ID</th>
          <th class="px-4 py-3 text-left">이메일</th>
          <th class="px-4 py-3 text-left">역할</th>
          <th class="px-4 py-3 text-left">상태</th>
          <th class="px-4 py-3 text-left">회사/담당</th>
          <th class="px-4 py-3 text-left">가입일</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td class="px-4 py-4 text-gray-500" colspan="6">데이터가 없습니다.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr class="border-t">
            <td class="px-4 py-3 font-mono">#<?= (int)$r['id'] ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($r['email'], ENT_QUOTES,'UTF-8') ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($r['role'], ENT_QUOTES,'UTF-8') ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($r['status'], ENT_QUOTES,'UTF-8') ?></td>
            <td class="px-4 py-3">
              <?= htmlspecialchars($r['company_name'] ?? '-', ENT_QUOTES,'UTF-8') ?> /
              <?= htmlspecialchars($r['contact_name'] ?? '-', ENT_QUOTES,'UTF-8') ?>
            </td>
            <td class="px-4 py-3"><?= htmlspecialchars($r['created_at'], ENT_QUOTES,'UTF-8') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>