<?php
// 공통 헤더(세션 포함)
require_once __DIR__ . '/inc/header.php';

/**
 * 추후 inc/csrf.php에서 $_SESSION['csrf']를 세팅한다고 가정.
 * 아직 없다면 아래 meta는 빈 값이어도 동작에는 문제 없습니다.
 */
$csrf = $_SESSION['csrf'] ?? '';
?>
<!-- CSRF 메타 (fetch 호출 시 헤더에 넣기 위해 DOM에서 읽음) -->
<meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

<div class="w-full max-w-5xl mx-auto p-6">
  <!-- 페이지 타이틀 -->
  <div class="mb-6">
    <h2 class="text-2xl font-bold">블로그 원고 생성</h2>
    <p class="text-gray-600 mt-1">회사/담당자 정보와 키워드를 입력하고, 승인된 광고 매체를 선택한 뒤 글을 생성하세요.</p>
  </div>

  <!-- 사용자 정보 & 키워드 폼 -->
  <form id="blog-form" class="bg-white rounded-xl shadow p-6 space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">회사명</label>
        <input type="text" name="company_name" id="company_name" class="w-full border rounded px-3 py-2" placeholder="예) 배트크루 주식회사" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">담당자명</label>
        <input type="text" name="contact_name" id="contact_name" class="w-full border rounded px-3 py-2" placeholder="예) 김마케팅" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
        <input type="text" name="contact_phone" id="contact_phone" class="w-full border rounded px-3 py-2" placeholder="예) 010-1234-5678">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">톤(tone)</label>
          <select name="tone" id="tone" class="w-full border rounded px-3 py-2">
            <option value="formal">formal (전문적)</option>
            <option value="friendly">friendly (친근함)</option>
            <option value="persuasive">persuasive (설득형)</option>
            <option value="neutral">neutral (중립)</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">길이(자수 가이드)</label>
          <input type="number" name="length_hint" id="length_hint" class="w-full border rounded px-3 py-2" value="3000" min="1000" step="100">
        </div>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">마케팅 키워드</label>
      <textarea name="keywords" id="keywords" class="w-full border rounded px-3 py-2 h-24" placeholder="예) 옥외광고, 전광판, 버스쉘터, 브랜드 인지도, 신규 매장 오픈" required></textarea>
      <p class="text-xs text-gray-500 mt-1">쉼표(,)로 구분하거나 자유롭게 작성하세요.</p>
    </div>
  </form>

  <!-- PDF 업로드 섹션 -->
  <section class="mt-6 bg-white rounded-xl shadow p-6">
    <h3 class="text-xl font-semibold mb-3">광고 매체 소개서 업로드 (PDF)</h3>
    <form id="upload-form" class="flex flex-col md:flex-row items-start md:items-center gap-3" onsubmit="return false;">
      <input type="file" id="pdf" name="pdf" accept="application/pdf" class="block w-full md:w-auto border rounded px-3 py-2">
      <button type="button" id="btn-upload" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-900">업로드</button>
      <span class="text-sm text-gray-500">최대 20MB, PDF만 허용 • 업로드 후 관리자가 승인하면 아래 목록에서 선택할 수 있습니다.</span>
    </form>
    <div id="upload-status" class="text-sm text-gray-600 mt-2"></div>
  </section>

  <!-- 승인된 매체 선택 섹션 -->
  <section class="mt-6 bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-xl font-semibold">승인된 광고 매체 선택</h3>
      <div class="flex items-center gap-2">
        <input id="search" type="text" class="border rounded px-3 py-2" placeholder="매체명 검색">
        <button id="refresh-media" class="px-3 py-2 border rounded hover:bg-gray-50">새로고침</button>
      </div>
    </div>

    <div id="media-list" class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[60vh] overflow-y-auto pr-1">
      <!-- AJAX로 렌더링 -->
    </div>

    <div class="mt-4 text-sm text-gray-500">
      <span id="media-count">0</span>개 매체 표시 중
    </div>
  </section>

  <!-- 액션 버튼 -->
  <div class="mt-6 flex items-center gap-3">
    <button id="btn-generate" class="bg-indigo-600 text-white px-5 py-2 rounded hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed">
      글 생성
    </button>
    <span id="gen-status" class="text-sm text-gray-600"></span>
  </div>

  <!-- 결과 표시 -->
  <section class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow p-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="font-semibold">생성 결과 (원문)</h4>
        <div class="flex items-center gap-2">
          <button id="copy-text" class="px-3 py-1 border rounded hover:bg-gray-50 text-sm">복사</button>
          <span id="char-count" class="text-xs text-gray-500">0자</span>
        </div>
      </div>
      <textarea id="result-raw" class="w-full h-80 border rounded p-3 font-mono text-sm"></textarea>
    </div>

    <div class="bg-white rounded-xl shadow p-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="font-semibold">서식 적용 미리보기</h4>
        <button id="toggle-prose" class="px-3 py-1 border rounded hover:bg-gray-50 text-sm">원문↔서식 토글</button>
      </div>
      <div id="result-prose" class="prose max-w-none leading-relaxed"></div>
    </div>
  </section>
</div>

<!-- 페이지 전용 스크립트 (순서 중요: main.js 먼저) -->
<script src="/js/main.js"></script>
<script src="/js/dashboard.js"></script>


<?php
require_once __DIR__ . '/inc/footer.php';