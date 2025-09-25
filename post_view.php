<?php
// post_view.php — 단건 상세보기 (내 글만 접근)
// 요청: GET id=...
declare(strict_types=1);
session_start();

// 인증 가드
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

require_once __DIR__ . '/inc/db.php'; // $pdo

// CSRF 토큰
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int)$_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  $pageTitle = '잘못된 요청';
  include __DIR__ . '/inc/header.php';
  echo '<div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-6 text-red-300">잘못된 요청입니다.</div>';
  include __DIR__ . '/inc/footer.php';
  exit;
}

// 단건 조회(내 글만)
$stmt = $pdo->prepare("
  SELECT id, user_id, keywords, generated_content, created_at
  FROM blog_posts
  WHERE id = :id AND user_id = :uid
  LIMIT 1
");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
$stmt->execute();
$post = $stmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = '상세보기 | AI 옥외광고 블로그 생성기';
include __DIR__ . '/inc/header.php';

if (!$post) {
  // (선택) 관리자라면 접근 허용하도록 확장 가능
  echo '<div class="rounded-2xl border border-yellow-500/30 bg-yellow-500/10 p-6 text-yellow-300">게시물을 찾을 수 없거나 접근 권한이 없습니다.</div>';
  echo '<p class="mt-6"><a class="text-indigo-400 underline" href="posts.php">목록으로</a></p>';
  include __DIR__ . '/inc/footer.php';
  exit;
}

$created = (new DateTime($post['created_at']))->setTimezone(new DateTimeZone('+09:00'));
$createdStr = $created->format('Y-m-d H:i');
$keywords   = htmlspecialchars((string)$post['keywords'], ENT_QUOTES, 'UTF-8');
$content    = (string)$post['generated_content']; // 전체 원문은 pre 태그에 그대로 출력(HTML태그가 있다면 이스케이프 고려)
?>
<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-semibold">블로그 원고 상세</h1>
  <a href="posts.php" class="text-sm text-indigo-400 hover:underline">목록으로</a>
</div>

<div class="space-y-4">
  <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-4">
    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
      <div>
        <dt class="text-gray-400">생성일</dt>
        <dd class="text-gray-200 mt-1"><?= $createdStr ?></dd>
      </div>
      <div class="md:col-span-2">
        <dt class="text-gray-400">키워드</dt>
        <dd class="text-gray-200 mt-1"><?= $keywords ?></dd>
      </div>
    </dl>
  </div>

  <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-4">
    <h2 class="text-lg font-semibold mb-3">전체 원고</h2>
    <pre id="postContent" class="whitespace-pre-wrap leading-relaxed text-gray-100 bg-gray-800/70 rounded-xl p-4 border border-gray-700"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></pre>

    <div class="mt-4 flex flex-wrap gap-3">
      <button id="btnCopy" type="button"
              class="rounded-xl bg-gray-700 hover:bg-gray-600 transition px-4 py-2 text-sm">
        원고 복사하기
      </button>

      <!-- 재생성(옵션): 동일 키워드/맥락으로 새로 생성, 새 게시물로 저장 -->
      <form id="regenForm" method="post" class="inline">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
        <button id="btnRegen" type="submit"
                class="rounded-xl bg-indigo-600 hover:bg-indigo-500 transition px-4 py-2 text-sm">
          이 내용으로 재생성
        </button>
      </form>

      <span id="msg" class="text-sm text-emerald-300 hidden"></span>
    </div>
    <p class="mt-2 text-xs text-gray-500">* 재생성은 기존 기록을 보존한 채 새 게시물을 추가합니다.</p>
  </div>
</div>

<script>
  // 복사 버튼
  const postText = document.getElementById('postContent')?.innerText || '';
  const btnCopy  = document.getElementById('btnCopy');
  const msgEl    = document.getElementById('msg');

  btnCopy?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(postText);
      msgEl.textContent = '원고가 클립보드에 복사되었습니다.';
      msgEl.classList.remove('hidden');
      setTimeout(() => msgEl.classList.add('hidden'), 2000);
    } catch {
      alert('복사에 실패했습니다. 브라우저 권한을 확인해주세요.');
    }
  });

  // 재생성(옵션): /api/regenerate.php 로 POST (JSON 응답 가정)
  const regenForm = document.getElementById('regenForm');
  const btnRegen  = document.getElementById('btnRegen');

  regenForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    btnRegen.disabled = true;
    btnRegen.textContent = '재생성 중…';

    try {
      const fd = new FormData(regenForm);
      const res = await fetch('api/regenerate.php', {
        method: 'POST',
        body: fd
      });
      if (!res.ok) throw new Error('서버 오류: ' + res.status);
      const data = await res.json();

      if (data?.success) {
        // 새 게시물 상세로 이동(또는 현재 페이지 갱신)
        if (data.post_id) {
          location.href = 'post_view.php?id=' + encodeURIComponent(data.post_id);
        } else {
          msgEl.textContent = '재생성이 완료되었습니다.';
          msgEl.classList.remove('hidden');
          setTimeout(() => msgEl.classList.add('hidden'), 2000);
        }
      } else {
        throw new Error(data?.message || '재생성에 실패했습니다.');
      }
    } catch (err) {
      alert(err?.message || '재생성 중 오류가 발생했습니다.');
    } finally {
      btnRegen.disabled = false;
      btnRegen.textContent = '이 내용으로 재생성';
    }
  });
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>