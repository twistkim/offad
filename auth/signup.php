<?php
require_once __DIR__ . '/../inc/_bootstrap.php';
require_once __DIR__ . '/../inc/header.php';

// CSRF 준비
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($csrf, $posted_csrf)) {
    $error = '보안 토큰이 유효하지 않습니다. 새로고침 후 다시 시도하세요.';
  } else {
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $password2    = $_POST['password2'] ?? '';
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');

    if (!$email || !$password || !$password2) {
      $error = '이메일과 비밀번호를 입력하세요.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = '이메일 형식이 올바르지 않습니다.';
    } elseif ($password !== $password2) {
      $error = '비밀번호 확인이 일치하지 않습니다.';
    } elseif (strlen($password) < 8) {
      $error = '비밀번호는 8자 이상이어야 합니다.';
    } else {
      try {
        // 이메일 중복 확인
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $chk->execute([':email' => $email]);
        if ((int)$chk->fetchColumn() > 0) {
          $error = '이미 가입된 이메일입니다.';
        } else {
          // 첫 사용자면 admin
          $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
          $role = ($cnt === 0) ? 'admin' : 'user';

          $stmt = $pdo->prepare("
            INSERT INTO users (email, password_hash, role, company_name, contact_name, status, created_at, updated_at)
            VALUES (:email, :hash, :role, :company_name, :contact_name, 'active', NOW(), NOW())
          ");
          $stmt->execute([
            ':email'        => $email,
            ':hash'         => password_hash($password, PASSWORD_DEFAULT),
            ':role'         => $role,
            ':company_name' => $company_name ?: null,
            ':contact_name' => $contact_name ?: null,
          ]);

          // 자동 로그인
          $uid = (int)$pdo->lastInsertId();
          $_SESSION['user'] = [
            'id'           => $uid,
            'email'        => $email,
            'role'         => $role,
            'company_name' => $company_name ?: null,
            'contact_name' => $contact_name ?: null,
          ];
          header('Location: /dashboard.php');
          exit;
        }
      } catch (Throwable $e) {
        $error = '회원가입 처리 중 오류가 발생했습니다.';
        error_log('[signup] ' . $e->getMessage());
      }
    }
  }
}
?>
<meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

<div class="w-full max-w-md mx-auto p-6">
  <h2 class="text-2xl font-bold mb-4">회원가입</h2>

  <?php if ($error): ?>
    <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php elseif ($ok): ?>
    <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="bg-white rounded-xl shadow p-6 space-y-4">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div>
      <label class="block text-sm text-gray-700 mb-1">이메일</label>
      <input type="email" name="email" class="w-full border rounded px-3 py-2" required>
    </div>
    <div>
      <label class="block text-sm text-gray-700 mb-1">비밀번호</label>
      <input type="password" name="password" class="w-full border rounded px-3 py-2" required minlength="8" placeholder="8자 이상">
    </div>
    <div>
      <label class="block text-sm text-gray-700 mb-1">비밀번호 확인</label>
      <input type="password" name="password2" class="w-full border rounded px-3 py-2" required minlength="8">
    </div>
    <div>
      <label class="block text-sm text-gray-700 mb-1">회사명 (선택)</label>
      <input type="text" name="company_name" class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm text-gray-700 mb-1">담당자명 (선택)</label>
      <input type="text" name="contact_name" class="w-full border rounded px-3 py-2">
    </div>

    <button class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">회원가입</button>
    <div class="text-sm text-gray-600 text-center">
      이미 계정이 있으신가요? <a class="text-indigo-600" href="/auth/login.php">로그인</a>
    </div>
  </form>
</div>

<script src="/js/main.js"></script>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>