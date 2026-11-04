<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Session expired, please try again.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $stmt->execute([trim((string) ($_POST['username'] ?? ''))]);
        $user = $stmt->fetch();
        if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = trim((string) $_POST['username']);
            header('Location: index.php');
            exit;
        }
        $error = 'Wrong username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Kanban Board</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%)}
  form{background:#fff;padding:2.5rem;border-radius:20px;width:320px;box-shadow:0 20px 40px rgba(0,0,0,.15)}
  h1{margin:0 0 1.5rem;font-size:1.5rem;color:#2d3748;text-align:center}
  label{display:block;font-size:.85rem;color:#4a5568;margin-bottom:.35rem}
  input{width:100%;box-sizing:border-box;padding:.8rem 1rem;border:2px solid #e2e8f0;border-radius:12px;margin-bottom:1rem;font-size:1rem}
  button{width:100%;padding:.9rem;border:0;border-radius:12px;background:#667eea;color:#fff;font-size:1rem;cursor:pointer}
  .error{background:#fed7d7;color:#9b2c2c;padding:.6rem .8rem;border-radius:10px;margin-bottom:1rem;font-size:.9rem}
</style>
</head>
<body>
<form method="post">
  <h1>Kanban Board</h1>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
  <label for="username">Username</label>
  <input id="username" name="username" required autofocus>
  <label for="password">Password</label>
  <input id="password" name="password" type="password" required>
  <button type="submit">Log in</button>
</form>
</body>
</html>
