<?php
require_once __DIR__ . '/includes/layout.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$name = $username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Name must be 2 to 100 characters.';
        }
        if (!preg_match('/^[A-Za-z0-9_.@+-]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3 to 50 characters: letters, numbers and _ . @ + - only.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            try {
                $st = db()->prepare('INSERT INTO users (name, username, password) VALUES (?, ?, ?)');
                $st->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
                flash('Account created. Please log in.', 'ok');
                redirect('login.php');
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) {      // duplicate key on the unique username index
                    $errors[] = 'That username is already taken.';
                } else {
                    throw $e;
                }
            }
        }
    }
}

page_header('Create account', false);
?>
<div class="card narrow">
  <h1>Create account</h1>
  <p class="muted">Register first, then log in to make your payment.</p>
  <?php foreach ($errors as $err): ?><div class="alert error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" value="<?= e($name) ?>" required maxlength="100">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= e($username) ?>" required maxlength="50">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
    <label for="confirm">Confirm password</label>
    <input type="password" id="confirm" name="confirm" required minlength="8" autocomplete="new-password">
    <button class="full" type="submit">Register</button>
  </form>
  <p class="muted" style="margin-top:16px">Already registered? <a href="login.php">Log in</a></p>
</div>
<?php page_footer();
