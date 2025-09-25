<?php
// login.php (공통 header/footer 사용 버전)
declare(strict_types=1);
session_start();

// 이미 로그인 시 메인으로
if (!empty($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

// DB 연결
$errors = [];
try {
  require_once __DIR__ . '/inc/db.php'; // $pdo 제공
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('DB 연결($pdo)이 준비되지 않았습니다. inc/db.php를 확인하세요.');
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo "<h1>DB 연결 오류</h1><pre style='white-space:pre-wrap;'>"
       .htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')."</pre>";
  exit;
}

// CSRF 토큰
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $errors[] = '유효하지 않은 요청입니다. 다시 시도해주세요.';
  }

  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '이메일 형식을 확인해주세요.';
  }
  if ($password === '' || strlen($password) < 6) {
    $errors[] = '비밀번호를 확인해주세요.';
  }

  if (!$errors) {
    try {
      $stmt = $pdo->prepare('SELECT id, email, name, company, password_hash FROM users WHERE email = :email LIMIT 1');
      $stmt->bindValue(':email', $email, PDO::PARAM_STR);
      $stmt->execute();
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      $ok = $user && password_verify($password, $user['password_hash']);
      if (!$ok) {
        usleep(300000); // 미세 지연(브루트포스 완화)
        $errors[] = '이메일 또는 비밀번호가 올바르지 않습니다.';
      } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = (string)$user['name'];
        $_SESSION['user_company'] = (string)$user['company'];
        header('Location: index.php');
        exit;
      }
    } catch (Throwable $e) {
      $errors[] = '로그인 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
      // error_log($e->getMessage());
    }
  }
}

// 렌더
$pageTitle = '로그인 | AI 옥외광고 블로그 생성기';
include __DIR__ . '/inc/header.php';

$csrf_token = $_SESSION['csrf_token'] ?? '';
$justRegistered = isset($_GET['registered']) && $_GET['registered'] === '1';
?>
<div class="max-w-md mx-auto">
  <h1 class="text-2xl font-semibold mb-6">로그인</h1>

  <?php if ($justRegistered): ?>
    <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-300">
      회원가입이 완료되었습니다. 이제 로그인하세요!
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4">
      <ul class="list-disc list-inside text-sm text-red-300">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" class="space-y-5" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

    <div>
      <label for="email" class="block text-sm mb-1">이메일</label>
      <input
        type="email" id="email" name="email" required
        class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
        placeholder="you@example.com"
        value="<?= isset($_POST['email']) ? htmlspecialchars((string)$_POST['email'], ENT_QUOTES, 'UTF-8') : '' ?>"
      >
    </div>

    <div>
      <label for="password" class="block text-sm mb-1">비밀번호</label>
      <input
        type="password" id="password" name="password" required minlength="6"
        class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
        placeholder="비밀번호"
      >
    </div>

    <button
      type="submit"
      class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-500 transition px-4 py-3 font-medium"
      onclick="this.disabled=true; this.innerText='로그인 중…'; this.form.submit();"
    >
      로그인
    </button>
  </form>

  <p class="mt-6 text-sm text-gray-400">
    아직 계정이 없나요?
    <a href="register.php" class="text-indigo-400 hover:underline">회원가입</a>
  </p>
</div>

<script>
  // 클라이언트 측 간단 검증(UX 보조)
  document.querySelector('form')?.addEventListener('submit', function (e) {
    const email = this.email.value.trim();
    const pw = this.password.value;
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      e.preventDefault(); alert('이메일 형식을 확인해주세요.'); return;
    }
    if (!pw || pw.length < 6) {
      e.preventDefault(); alert('비밀번호는 6자 이상이어야 합니다.'); return;
    }
  });
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>