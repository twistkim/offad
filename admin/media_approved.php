<?php
require_once __DIR__ . '/../inc/_bootstrap.php';
require_once __DIR__ . '/../inc/header.php';

// CSRF
$csrf = $_SESSION['csrf'] ?? '';

// 승인된 PDF + 처리된 매체 요약 카운트
$sql = "
SELECT mk.id, mk.original_filename, mk.reviewed_at, u.email AS uploader_email,
       (SELECT COUNT(*) FROM processed_media pm WHERE pm.media_kit_id = mk.id) AS cnt_media
FROM media_kits mk
JOIN users u ON u.id = mk.user_id
WHERE mk.status='approved'
ORDER BY mk.reviewed_at DESC
LIMIT 200
";
$rows = $pdo->query($sql)->fetchAll();

// 상세 목록용(옵션): 처리된 매체 최근 50개 프리뷰
$sql2 = "
SELECT pm.id, pm.media_kit_id, pm.name, LEFT(pm.description, 120) AS desc_short, pm.created_at
FROM processed_media pm
ORDER BY pm.created_at DESC
LIMIT 50
";
$pm_rows = $pdo->query($sql2)->fetchAll();
?>
<meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES,'UTF-8') ?>">

<div class="w-full max-w-6xl mx-auto p-6 space-y-8">
  <div class="flex items-center justify-between">
    <h2 class="text-2xl font-bold">승인 완료 PDF</h2>
    <button id="btn-refresh" class="px-3 py-2 border rounded hover:bg-gray-50">새로고침</button>
  </div>

  <!-- 승인 완료 PDF 테이블 -->
  <div class="bg-white rounded-xl shadow overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left">PDF ID</th>
          <th class="px-4 py-3 text-left">파일명</th>
          <th class="px-4 py-3 text-left">업로더</th>
          <th class="px-4 py-3 text-left">매체 수</th>
          <th class="px-4 py-3 text-left">승인일</th>
          <th class="px-4 py-3 text-left">액션</th>
        </tr>
      </thead>
      <tbody id="approved-tbody">
        <?php if (!$rows): ?>
          <tr><td class="px-4 py-4 text-gray-500" colspan="6">승인된 항목이 없습니다.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr data-id="<?= (int)$r['id'] ?>" class="border-t">
            <td class="px-4 py-3 font-mono">#<?= (int)$r['id'] ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($r['original_filename'], ENT_QUOTES,'UTF-8') ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($r['uploader_email'], ENT_QUOTES,'UTF-8') ?></td>
            <td class="px-4 py-3"><?= (int)$r['cnt_media'] ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($r['reviewed_at'], ENT_QUOTES,'UTF-8') ?></td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <button class="btn-reprocess px-3 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-700"
                        data-id="<?= (int)$r['id'] ?>">다시 분석</button>
              </div>
              <div class="text-xs text-gray-500 mt-1 status-line"></div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- 최근 처리된 매체 프리뷰 -->
  <div>
    <h3 class="text-xl font-semibold mb-3">최근 처리된 매체 (50)</h3>
    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">ID</th>
            <th class="px-4 py-3 text-left">PDF ID</th>
            <th class="px-4 py-3 text-left">매체명</th>
            <th class="px-4 py-3 text-left">설명</th>
            <th class="px-4 py-3 text-left">생성일</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$pm_rows): ?>
            <tr><td class="px-4 py-4 text-gray-500" colspan="5">데이터가 없습니다.</td></tr>
          <?php else: foreach ($pm_rows as $pm): ?>
            <tr class="border-t">
              <td class="px-4 py-3 font-mono">#<?= (int)$pm['id'] ?></td>
              <td class="px-4 py-3">#<?= (int)$pm['media_kit_id'] ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($pm['name'], ENT_QUOTES,'UTF-8') ?></td>
              <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($pm['desc_short'] ?? '', ENT_QUOTES,'UTF-8') ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($pm['created_at'] ?? '', ENT_QUOTES,'UTF-8') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="/js/admin.js"></script>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>