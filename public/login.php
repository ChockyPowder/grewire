<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

if (current_user()) {
    header('Location: /');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        try {
            Grewire\Auth::login(db()->pdo(), $username, $password);
            header('Location: /');
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · Grewire</title>
<link rel="stylesheet" href="/assets/app.css?v=2026092706">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="brand auth-brand">Grewire</div>
    <div class="auth-title">Login</div>
    <div class="auth-subtitle">Sign in to your Grewire account.</div>
    <?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" class="auth-form">
      <label>Username<input name="username" maxlength="32" autocomplete="username" required></label>
      <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
      <button class="btn btn-primary auth-submit" type="submit">Login</button>
    </form>
    <div class="auth-switch">Don't have an account? <a href="/register.php">Register</a></div>
  </div>
</div>
</body>
</html>
