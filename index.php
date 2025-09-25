<?php
// index.php — 메인 대시보드 & 생성 폼 (키워드/이름/회사만 입력)
// 서버는 관리자 구글 드라이브 자료를 내부적으로 사용해 자동 생성
declare(strict_types=1);
session_start();

// 인증 가드
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

// CSRF 토큰
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 세션의 기본값(수정 가능)
$userName    = $_SESSION['user_name'] ?? '';
$userCompany = $_SESSION['user_company'] ?? '';

$pageTitle = '대시보드 | AI 옥외광고 블로그 생성기';
include __DIR__ . '/inc/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
  <!-- 생성 폼 -->
  <section class="bg-gray-900/60 backdrop-blur rounded-2xl p-6 shadow-xl border border-gray-800">
    <h2 class="text-xl font-semibold mb-4">블로그 원고 생성</h2>

    <form id="genForm" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

      <div>
        <label class="block text-sm mb-1" for="keywords">핵심 키워드 (쉼표로 구분)</label>
        <textarea id="keywords" name="keywords" rows="4" required
                  class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="디지털 사이니지, 버스쉘터 광고, 지하철 스크린도어 광고"></textarea>
        <p class="mt-1 text-xs text-gray-400">예시: “옥외광고, 기업브랜딩, 신규오픈 프로모션”</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1" for="salesperson_name">영업사원 이름</label>
          <input id="salesperson_name" name="salesperson_name" type="text" required
                 class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
                 placeholder="홍길동"
                 value="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
          <label class="block text-sm mb-1" for="company_name">회사명</label>
          <input id="company_name" name="company_name" type="text" required
                 class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
                 placeholder="오프애드"
                 value="<?= htmlspecialchars($userCompany, ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button id="submitBtn" type="submit"
                class="rounded-xl bg-indigo-600 hover:bg-indigo-500 transition px-5 py-3 font-medium">
          블로그 작성하기
        </button>
        <div id="loading" class="hidden flex items-center gap-2 text-sm text-gray-300">
          <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle>
            <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="4"></path>
          </svg>
          <span>원고 생성 중…</span>
        </div>
      </div>

      <p id="formMsg" class="mt-2 text-sm text-red-300 hidden"></p>
      <p class="mt-2 text-xs text-gray-500">
        * 매체 자료는 관리자의 구글 드라이브 내부 자료를 자동 참조합니다. 별도 업로드가 필요 없습니다.
      </p>
    </form>
  </section>

  <!-- 결과 영역 -->
  <section class="bg-gray-900/60 backdrop-blur rounded-2xl p-6 shadow-xl border border-gray-800">
    <h2 class="text-xl font-semibold mb-4">생성 결과</h2>
    <div id="resultEmpty" class="text-sm text-gray-400">아직 생성된 원고가 없습니다.</div>

    <div id="resultWrap" class="hidden space-y-4">
      <h3 id="resultTitle" class="text-lg font-semibold"></h3>
      <pre id="resultContent" class="whitespace-pre-wrap leading-relaxed text-gray-100 bg-gray-800/70 rounded-xl p-4 border border-gray-700"></pre>

      <div class="flex flex-wrap gap-3">
        <button id="btnCopy" type="button"
                class="rounded-xl bg-gray-700 hover:bg-gray-600 transition px-4 py-2 text-sm">
          원고 복사하기
        </button>
        <button id="btnShare" type="button"
                class="rounded-xl bg-gray-700 hover:bg-gray-600 transition px-4 py-2 text-sm">
          추천 링크 복사
        </button>
      </div>

      <p id="resultMsg" class="text-sm text-emerald-300 hidden"></p>
    </div>
  </section>
</div>

<script>
  const form         = document.getElementById('genForm');
  const submitBtn    = document.getElementById('submitBtn');
  const loadingEl    = document.getElementById('loading');
  const formMsg      = document.getElementById('formMsg');

  const resultEmpty   = document.getElementById('resultEmpty');
  const resultWrap    = document.getElementById('resultWrap');
  const resultTitle   = document.getElementById('resultTitle');
  const resultContent = document.getElementById('resultContent');
  const resultMsg     = document.getElementById('resultMsg');
  const btnCopy       = document.getElementById('btnCopy');
  const btnShare      = document.getElementById('btnShare');

  function validate() {
    const keywords = document.getElementById('keywords').value.trim();
    const name     = document.getElementById('salesperson_name').value.trim();
    const company  = document.getElementById('company_name').value.trim();

    if (!keywords) return '핵심 키워드를 입력해주세요.';
    if (!name)     return '영업사원 이름을 입력해주세요.';
    if (!company)  return '회사명을 입력해주세요.';
    return '';
  }

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    formMsg.classList.add('hidden');

    const v = validate();
    if (v) {
      formMsg.textContent = v;
      formMsg.classList.remove('hidden');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = '생성 중…';
    loadingEl.classList.remove('hidden');

    try {
      const fd = new FormData(form);
      // 서버는 내부적으로 관리자 드라이브 자료를 참조해 프롬프트를 구성합니다.
      const res = await fetch('api/generate.php', {
        method: 'POST',
        body: fd,
      });

      if (!res.ok) {
        throw new Error('서버 응답이 올바르지 않습니다. (' + res.status + ')');
      }

      const data = await res.json();
      if (!data?.success) {
        throw new Error(data?.message || '생성에 실패했습니다.');
      }

      // 성공 렌더
      const postText = data.post || '';
      const postId   = data.post_id || data.id || null;

      resultEmpty.classList.add('hidden');
      resultWrap.classList.remove('hidden');

      // 제목: 첫 비어있지 않은 줄을 제목으로
      const firstLine = postText.split('\n').find(line => line.trim().length > 0) || '';
      const title = firstLine.replace(/^#+\s*/, '').slice(0, 80) || '생성된 블로그 원고';

      resultTitle.textContent = title;
      resultContent.textContent = postText;

      btnCopy.onclick = async () => {
        try {
          await navigator.clipboard.writeText(postText);
          resultMsg.textContent = '원고가 클립보드에 복사되었습니다.';
          resultMsg.classList.remove('hidden');
          setTimeout(() => resultMsg.classList.add('hidden'), 2000);
        } catch {
          alert('복사에 실패했습니다.');
        }
      };

      btnShare.onclick = async () => {
        let shareUrl = location.origin + location.pathname.replace(/\/index\.php$/, '/') + 'post_view.php';
        if (postId) shareUrl += '?id=' + encodeURIComponent(postId);
        try {
          await navigator.clipboard.writeText(shareUrl);
          resultMsg.textContent = '추천 링크가 복사되었습니다.';
          resultMsg.classList.remove('hidden');
          setTimeout(() => resultMsg.classList.add('hidden'), 2000);
        } catch {
          alert('클립보드 복사에 실패했습니다.');
        }
      };

    } catch (err) {
      formMsg.textContent = (err && err.message) ? err.message : '알 수 없는 오류가 발생했습니다.';
      formMsg.classList.remove('hidden');
    } finally {
      loadingEl.classList.add('hidden');
      submitBtn.disabled = false;
      submitBtn.textContent = '블로그 작성하기';
    }
  });
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>