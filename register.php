<?php
// register.php (공통 header/footer 사용 버전)
declare(strict_types=1);
session_start();

// 로그인 상태면 메인으로
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

  $email    = trim((string)($_POST['email'] ?? ''));
  $name     = trim((string)($_POST['name'] ?? ''));
  $company  = trim((string)($_POST['company'] ?? ''));
  $phone    = trim((string)($_POST['phone'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $pwConfirm= (string)($_POST['password_confirm'] ?? '');

  // 서버측 검증
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '이메일 형식을 확인해주세요.';
  }
  if ($name === '' || mb_strlen($name) < 2) {
    $errors[] = '이름을 2자 이상 입력해주세요.';
  }
  if ($company === '') {
    $errors[] = '회사명을 입력해주세요.';
  }
  if ($phone === '' || !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
    $errors[] = '연락처 형식을 확인해주세요.';
  }
  if ($password === '' || strlen($password) < 6) {
    $errors[] = '비밀번호는 6자 이상이어야 합니다.';
  }
  if ($password !== $pwConfirm) {
    $errors[] = '비밀번호 확인이 일치하지 않습니다.';
  }

  if (!$errors) {
    try {
      // 이메일 중복 확인
      $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
      $stmt->bindValue(':email', $email, PDO::PARAM_STR);
      $stmt->execute();
      if ($stmt->fetchColumn()) {
        $errors[] = '이미 사용 중인 이메일입니다.';
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $ins = $pdo->prepare('
          INSERT INTO users (email, name, company, phone, password_hash)
          VALUES (:email, :name, :company, :phone, :password_hash)
        ');
        $ins->execute([
          ':email' => $email,
          ':name'  => $name,
          ':company' => $company,
          ':phone' => $phone,
          ':password_hash' => $hash,
        ]);

        header('Location: login.php?registered=1');
        exit;
      }
    } catch (Throwable $e) {
      $errors[] = '회원가입 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
      // error_log($e->getMessage());
    }
  }
}

// 렌더
$pageTitle = '회원가입 | AI 옥외광고 블로그 생성기';
include __DIR__ . '/inc/header.php';

$csrf_token = $_SESSION['csrf_token'] ?? '';
?>
<div class="max-w-lg mx-auto">
  <h1 class="text-2xl font-semibold mb-6">회원가입</h1>

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
      <input type="email" id="email" name="email" required
             class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
             placeholder="you@example.com"
             value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : '' ?>">
    </div>

    <div>
      <label for="name" class="block text-sm mb-1">이름</label>
      <input type="text" id="name" name="name" required
             class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
             placeholder="홍길동"
             value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8') : '' ?>">
    </div>

    <div>
      <label for="company" class="block text-sm mb-1">회사명</label>
      <input type="text" id="company" name="company" required
             class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
             placeholder="오프애드"
             value="<?= isset($_POST['company']) ? htmlspecialchars($_POST['company'], ENT_QUOTES, 'UTF-8') : '' ?>">
    </div>

    <div>
      <label for="phone" class="block text-sm mb-1">연락처</label>
      <input type="text" id="phone" name="phone" required
             class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
             placeholder="010-1234-5678"
             value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8') : '' ?>">
    </div>

    <div>
      <label for="password" class="block text-sm mb-1">비밀번호</label>
      <input type="password" id="password" name="password" required minlength="6"
             class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
             placeholder="6자 이상">
    </div>

    <div>
      <label for="password_confirm" class="block text-sm mb-1">비밀번호 확인</label>
      <input type="password" id="password_confirm" name="password_confirm" required minlength="6"
             class="w-full rounded-xl bg-gray-800 border border-gray-700 px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500"
             placeholder="다시 입력">
    </div>

    <button type="submit"
      class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-500 transition px-4 py-3 font-medium"
      onclick="this.disabled=true; this.innerText='가입 처리 중…'; this.form.submit();">
      회원가입
    </button>
  </form>

  <p class="mt-6 text-sm text-gray-400">
    이미 계정이 있나요?
    <a href="login.php" class="text-indigo-400 hover:underline">로그인</a>
  </p>
</div>

<script>
  // 간단한 클라이언트 검증
  document.querySelector('form')?.addEventListener('submit', function (e) {
    const email = this.email.value.trim();
    const name = this.name.value.trim();
    const company = this.company.value.trim();
    const phone = this.phone.value.trim();
    const pw = this.password.value;
    const pwc = this.password_confirm.value;

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      e.preventDefault(); alert('이메일 형식을 확인해주세요.'); return;
    }
    if (!name || name.length < 2) {
      e.preventDefault(); alert('이름을 2자 이상 입력해주세요.'); return;
    }
    if (!company) {
      e.preventDefault(); alert('회사명을 입력해주세요.'); return;
    }
    if (!phone || !/^[0-9+\-\s()]{7,20}$/.test(phone)) {
      e.preventDefault(); alert('연락처 형식을 확인해주세요.'); return;
    }
    if (!pw || pw.length < 6) {
      e.preventDefault(); alert('비밀번호는 6자 이상이어야 합니다.'); return;
    }
    if (pw !== pwc) {
      e.preventDefault(); alert('비밀번호 확인이 일치하지 않습니다.'); return;
    }
  });
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>