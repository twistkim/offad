<?php
require_once __DIR__ . '/inc/_bootstrap.php';
require_login();
$pageTitle = '마이 페이지';
include __DIR__ . '/inc/header.php';
?>
<main class="max-w-5xl mx-auto p-4">
  <h1 class="text-2xl font-bold mb-4">내 블로그 원고</h1>

  <!-- 검색/필터 -->
  <div class="flex items-center gap-2 mb-3">
    <input id="q" type="text" placeholder="키워드 검색" class="border rounded px-3 py-2 w-64">
    <button id="btn-search" class="px-3 py-2 border rounded">검색</button>
  </div>

  <!-- 목록 -->
  <div class="bg-white rounded-xl shadow">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-3">요청 ID</th>
          <th class="text-left p-3">회사/담당</th>
          <th class="text-left p-3">키워드</th>
          <th class="text-left p-3">상태</th>
          <th class="text-left p-3">생성일</th>
          <th class="text-left p-3">보기</th>
        </tr>
      </thead>
      <tbody id="my-rows"></tbody>
    </table>
  </div>

  <!-- 페이징 -->
  <div class="flex items-center gap-2 mt-3">
    <button id="prev" class="px-3 py-1 border rounded">이전</button>
    <span id="pageinfo" class="text-sm text-gray-600"></span>
    <button id="next" class="px-3 py-1 border rounded">다음</button>
  </div>
</main>

<!-- 상세 모달 -->
<div id="dlg" class="fixed inset-0 hidden items-center justify-center bg-black/40">
  <div class="bg-white max-w-3xl w-[90%] max-h-[90vh] overflow-y-auto rounded-xl shadow p-4">
    <div class="flex justify-between items-center mb-2 sticky top-0 bg-white">
      <h3 class="text-xl font-semibold">블로그 원고</h3>
      <button id="dlg-close" class="px-2 py-1 border rounded">닫기</button>
    </div>
    <div id="dlg-body" class="prose max-w-none text-sm whitespace-pre-wrap max-h-[70vh] overflow-y-auto"></div>
  </div>
</div>

<script src="/js/main.js"></script>
<script src="/js/mypage.js"></script>
<?php include __DIR__ . '/inc/footer.php'; ?>