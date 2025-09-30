<?php
require_once __DIR__ . '/../inc/_bootstrap.php';
require_once __DIR__ . '/../inc/header.php';

// CSRF
$csrf = $_SESSION['csrf'] ?? '';

// 간단 목록 (최근 업로드 순)
$sql = "
SELECT mk.id, mk.original_filename, mk.stored_filename, mk.mime_type, mk.file_size, mk.uploaded_at,
       u.email AS uploader_email, u.company_name
FROM media_kits mk
JOIN users u ON u.id = mk.user_id
WHERE mk.status='pending'
ORDER BY mk.uploaded_at DESC
LIMIT 200
";
$rows = $pdo->query($sql)->fetchAll();
?>
<meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES,'UTF-8') ?>">

<div class="w-full max-w-6xl mx-auto p-6 space-y-6">
  <div class="flex items-center justify-between">
    <h2 class="text-2xl font-bold">승인 대기 PDF</h2>
    <button id="btn-refresh" class="px-3 py-2 border rounded hover:bg-gray-50">새로고침</button>
  </div>

  <div class="bg-white rounded-xl shadow overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left">ID</th>
          <th class="px-4 py-3 text-left">파일명</th>
          <th class="px-4 py-3 text-left">업로더</th>
          <th class="px-4 py-3 text-left">용량</th>
          <th class="px-4 py-3 text-left">업로드일</th>
          <th class="px-4 py-3 text-left">액션</th>
        </tr>
      </thead>
      <tbody id="pending-tbody">
        <?php if (!$rows): ?>
          <tr><td class="px-4 py-4 text-gray-500" colspan="6">승인 대기 항목이 없습니다.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr data-id="<?= (int)$r['id'] ?>" class="border-t">
            <td class="px-4 py-3 font-mono">#<?= (int)$r['id'] ?></td>
            <td class="px-4 py-3">
              <div class="font-medium"><?= htmlspecialchars($r['original_filename'], ENT_QUOTES,'UTF-8') ?></div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($r['mime_type'], ENT_QUOTES,'UTF-8') ?></div>
            </td>
            <td class="px-4 py-3">
              <div><?= htmlspecialchars($r['uploader_email'], ENT_QUOTES,'UTF-8') ?></div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($r['company_name'] ?? '-', ENT_QUOTES,'UTF-8') ?></div>
            </td>
            <td class="px-4 py-3"><?= number_format((int)$r['file_size']) ?> B</td>
            <td class="px-4 py-3"><?= htmlspecialchars($r['uploaded_at'], ENT_QUOTES,'UTF-8') ?></td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <!-- 승인만 -->
                <button class="btn-approve px-3 py-1 rounded bg-emerald-600 text-white hover:bg-emerald-700"
                        data-id="<?= (int)$r['id'] ?>">승인</button>
                <!-- 승인 + 분석 -->
                <button class="btn-approve-analyze px-3 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-700"
                        data-id="<?= (int)$r['id'] ?>">승인+분석</button>
                <!-- 반려 -->
                <button class="btn-reject px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700"
                        data-id="<?= (int)$r['id'] ?>">반려</button>
              </div>
              <div class="text-xs text-gray-500 mt-1 status-line"></div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="/js/admin.js"></script>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>