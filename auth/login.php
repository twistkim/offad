<?php
require_once __DIR__ . '/../inc/_bootstrap.php';
require_once __DIR__ . '/../inc/header.php';

// CSRF 준비
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($csrf, $posted_csrf)) {
    $error = '보안 토큰이 유효하지 않습니다. 새로고침 후 다시 시도하세요.';
  } else {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
      $error = '이메일과 비밀번호를 입력하세요.';
    } else {
      try {
        $stmt = $pdo->prepare("SELECT id, email, password_hash, role, company_name, contact_name, status
                                 FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
          $error = '이메일 또는 비밀번호가 올바르지 않습니다.';
        } elseif ($user['status'] !== 'active') {
          $error = '비활성화된 계정입니다. 관리자에게 문의하세요.';
        } else {
          // 로그인 성공
          $_SESSION['user'] = [
            'id'           => (int)$user['id'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'company_name' => $user['company_name'],
            'contact_name' => $user['contact_name'],
          ];
          // 원래 가려던 곳으로(옵션)
          $redirect = !empty($_GET['redirect']) ? $_GET['redirect'] : '/dashboard.php';
          header("Location: " . $redirect);
          exit;
        }
      } catch (Throwable $e) {
        $error = '로그인 처리 중 오류가 발생했습니다.';
        error_log('[login] ' . $e->getMessage());
      }
    }
  }
}
?>
<meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

<div class="w-full max-w-md mx-auto p-6">
  <h2 class="text-2xl font-bold mb-4">로그인</h2>

  <?php if ($error): ?>
    <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="bg-white rounded-xl shadow p-6 space-y-4">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div>
      <label class="block text-sm text-gray-700 mb-1">이메일</label>
      <input type="email" name="email" class="w-full border rounded px-3 py-2" required>
    </div>
    <div>
      <label class="block text-sm text-gray-700 mb-1">비밀번호</label>
      <input type="password" name="password" class="w-full border rounded px-3 py-2" required>
    </div>
    <button class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">로그인</button>
    <div class="text-sm text-gray-600 text-center">
      아직 계정이 없으신가요? <a class="text-indigo-600" href="/auth/signup.php">회원가입</a>
    </div>
  </form>
</div>

<script src="/js/main.js"></script>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>