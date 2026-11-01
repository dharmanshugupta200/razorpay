<?php
require_once __DIR__ . '/includes/layout.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';
$now = time();

// Simple brute-force brake: 5 wrong passwords => wait 60 seconds (per browser session).
$blockedUntil = (int)($_SESSION['login_block_until'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($blockedUntil > $now) {
        $error = 'Too many attempts. Try again in ' . ($blockedUntil - $now) . ' seconds.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $st = db()->prepare('SELECT id, password FROM users WHERE username = ?');
        $st->execute([$username]);
        $u = $st->fetch();

        // Always run password_verify so "unknown user" and "wrong password" take the same time.
        $hash = $u['password'] ?? password_hash('not-a-real-user', PASSWORD_DEFAULT);
        if ($u && password_verify($password, $hash)) {
            session_regenerate_id(true);                 // stop session fixation
            $_SESSION['user_id'] = (int)$u['id'];
            unset($_SESSION['login_fails'], $_SESSION['login_block_until']);
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
            }
            redirect('dashboard.php');
        }

        $_SESSION['login_fails'] = (int)($_SESSION['login_fails'] ?? 0) + 1;
        if ($_SESSION['login_fails'] >= 5) {
            $_SESSION['login_block_until'] = $now + 60;
            $_SESSION['login_fails'] = 0;
        }
        $error = 'Wrong username or password.';
    }
}

page_header('Log in', false);
?>
<div class="card narrow">
  <h1>Log in</h1>
  <p class="muted">Log in to make or view your payment.</p>
  <?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">
    <button class="full" type="submit">Log in</button>
  </form>
  <p class="muted" style="margin-top:16px">New here? <a href="register.php">Create an account</a></p>
</div>
<?php page_footer();
