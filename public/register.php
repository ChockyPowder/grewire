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
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        $error = 'Username must be 3–32 characters and use only letters, numbers, and underscores.';
    } elseif ($displayName === '' || mb_strlen($displayName) > 64) {
        $error = 'Display name must be between 1 and 64 characters.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $user = Grewire\Auth::register(db()->pdo(), $username, $displayName, $password);
            Grewire\DevWorkspace::ensure(db()->pdo(), $user['username']);
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
<title>Register · Grewire</title>
<link rel="stylesheet" href="/assets/app.css?v=2026092706">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="brand auth-brand">Grewire</div>
    <div class="auth-title">Create an account</div>
    <div class="auth-subtitle">Join Grewire and start chatting.</div>
    <?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" class="auth-form">
      <label>Username<input name="username" maxlength="32" autocomplete="username" required></label>
      <label>Display name<input name="display_name" maxlength="64" autocomplete="name" required></label>
      <label>Password<input name="password" type="password" autocomplete="new-password" required></label>
      <label>Confirm password<input name="password_confirm" type="password" autocomplete="new-password" required></label>
      <button class="btn btn-primary auth-submit" type="submit">Register</button>
    </form>
    <div class="auth-switch">Already have an account? <a href="/login.php">Login</a></div>
  </div>
</div>
</body>
</html>
