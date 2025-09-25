<?php
// posts.php — 내 생성 기록 목록
declare(strict_types=1);
session_start();

// 인증 가드
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

require_once __DIR__ . '/inc/db.php'; // $pdo

$userId = (int)$_SESSION['user_id'];

// 페이지네이션 파라미터
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

// 총 개수
$total = 0;
$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM blog_posts WHERE user_id = :uid');
$stmtCount->bindValue(':uid', $userId, PDO::PARAM_INT);
$stmtCount->execute();
$total = (int)$stmtCount->fetchColumn();

$totalPages = max(1, (int)ceil($total / $perPage));

// 목록 조회 (최신순)
$stmt = $pdo->prepare("
  SELECT id, keywords, generated_content, created_at
  FROM blog_posts
  WHERE user_id = :uid
  ORDER BY created_at DESC, id DESC
  LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 미리보기 생성 헬퍼
function make_preview(string $text, int $len = 140): string {
  // 간단한 마크다운 헤더/링크 표시 제거
  $text = preg_replace('/^#+\s*/m', '', $text);        // # 제목 제거
  $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', $text); // 이미지 문법 제거
  $text = preg_replace('/\[[^\]]*\]\([^)]+\)/', '$0', $text); // 링크 텍스트만 두고 싶으면 수정
  $text = strip_tags($text);
  $text = trim(preg_replace('/\s+/', ' ', $text));
  if (mb_strlen($text, 'UTF-8') > $len) {
    return mb_substr($text, 0, $len, 'UTF-8') . '…';
  }
  return $text;
}

$pageTitle = '내 생성 기록 | AI 옥외광고 블로그 생성기';
include __DIR__ . '/inc/header.php';
?>

<h1 class="text-2xl font-semibold mb-6">내 생성 기록</h1>

<?php if ($total === 0): ?>
  <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6 text-gray-300">
    아직 생성한 원고가 없습니다. <a class="text-indigo-400 underline" href="index.php">여기</a>에서 원고를 만들어보세요.
  </div>
<?php else: ?>
  <div class="overflow-hidden rounded-2xl border border-gray-800 bg-gray-900/60">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-900/80 text-gray-300">
        <tr>
          <th class="px-4 py-3 text-left font-medium">생성일</th>
          <th class="px-4 py-3 text-left font-medium">키워드</th>
          <th class="px-4 py-3 text-left font-medium">미리보기</th>
          <th class="px-4 py-3 text-left font-medium">액션</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-800">
        <?php foreach ($rows as $r): ?>
          <?php
            $created = (new DateTime($r['created_at']))->setTimezone(new DateTimeZone('+09:00'));
            $createdStr = $created->format('Y-m-d H:i');
            $kw = htmlspecialchars($r['keywords'] ?? '', ENT_QUOTES, 'UTF-8');
            $preview = htmlspecialchars(make_preview((string)$r['generated_content']), ENT_QUOTES, 'UTF-8');
            $id = (int)$r['id'];
          ?>
          <tr class="hover:bg-gray-800/40">
            <td class="px-4 py-3 align-top text-gray-300 whitespace-nowrap"><?= $createdStr ?></td>
            <td class="px-4 py-3 align-top text-gray-100"><?= $kw ?></td>
            <td class="px-4 py-3 align-top text-gray-400"><?= $preview ?></td>
            <td class="px-4 py-3 align-top">
              <a href="post_view.php?id=<?= $id ?>"
                 class="inline-block rounded-lg bg-indigo-600 hover:bg-indigo-500 px-3 py-1 text-xs text-white">
                상세보기
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- 페이지네이션 -->
  <?php if ($totalPages > 1): ?>
    <div class="mt-6 flex items-center justify-between text-sm">
      <div class="text-gray-400">
        총 <?= number_format($total) ?>건 · <?= $page ?>/<?= $totalPages ?> 페이지
      </div>
      <div class="flex items-center gap-2">
        <?php
          $base = strtok($_SERVER['REQUEST_URI'], '?') ?: '/posts.php';
          // 현재 쿼리스트링 보존(필요 시 확장)
          $build = function(int $p) use ($base) {
            return htmlspecialchars($base . '?page=' . $p, ENT_QUOTES, 'UTF-8');
          };
        ?>
        <a href="<?= $build(1) ?>" class="px-3 py-1 rounded-lg border border-gray-700 hover:bg-gray-800 <?= $page==1?'opacity-50 pointer-events-none':'' ?>">처음</a>
        <a href="<?= $build(max(1, $page-1)) ?>" class="px-3 py-1 rounded-lg border border-gray-700 hover:bg-gray-800 <?= $page==1?'opacity-50 pointer-events-none':'' ?>">이전</a>
        <a href="<?= $build(min($totalPages, $page+1)) ?>" class="px-3 py-1 rounded-lg border border-gray-700 hover:bg-gray-800 <?= $page==$totalPages?'opacity-50 pointer-events-none':'' ?>">다음</a>
        <a href="<?= $build($totalPages) ?>" class="px-3 py-1 rounded-lg border border-gray-700 hover:bg-gray-800 <?= $page==$totalPages?'opacity-50 pointer-events-none':'' ?>">마지막</a>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>