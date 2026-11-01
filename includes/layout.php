<?php

require_once __DIR__ . '/bootstrap.php';

function page_header(string $title, bool $showNavigation = true): void
{
    $notice = flash();
    $user = $showNavigation ? current_user() : null;
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | Payment Practice</title>
  <style>
    :root{font-family:Arial,sans-serif;color:#202938;background:#f4f6fa;--bad:#b42318}
    *{box-sizing:border-box}
    body{margin:0}
    header{background:#fff;border-bottom:1px solid #e3e7ef}
    .nav,main{width:min(960px,calc(100% - 32px));margin:auto}
    .nav{min-height:60px;display:flex;align-items:center;justify-content:space-between;gap:16px}
    .nav a{color:#2f6fed;text-decoration:none}
    main{padding:28px 0 48px}
    .card{background:#fff;border:1px solid #e3e7ef;border-radius:12px;padding:24px;margin:0 0 20px;box-shadow:0 4px 16px #2029380a}
    .narrow{max-width:480px;margin:32px auto}
    h1,h2{margin-top:0}
    .muted{color:#667085}
    label{display:block;font-weight:600;margin:16px 0 6px}
    input{width:100%;padding:11px 12px;border:1px solid #cbd2df;border-radius:6px;font:inherit}
    button{padding:12px 20px;border:0;border-radius:6px;background:#2f6fed;color:#fff;font:inherit;font-weight:600;cursor:pointer}
    button:disabled{opacity:.6;cursor:wait}
    .full{width:100%;margin-top:20px}
    .alert{padding:12px 14px;border-radius:7px;margin:12px 0}
    .alert.error{color:#8f1d16;background:#fff0ee}
    .alert.ok{color:#176b3a;background:#eaf8ef}
    .alert.warn{color:#805600;background:#fff7df}
    .alert.info{color:#2456a6;background:#eef4ff}
    table{width:100%;border-collapse:collapse}
    th,td{text-align:left;padding:10px;border-bottom:1px solid #e8ebf1;vertical-align:top}
    .amount{font-size:2rem;font-weight:700;margin:12px 0 20px}
    .scroll{overflow-x:auto}
    .badge{display:inline-block;padding:4px 8px;border-radius:99px;background:#eef1f6}
    .b-success{background:#eaf8ef;color:#176b3a}
    .b-failed{background:#fff0ee;color:#8f1d16}
    .b-cancelled,.b-expired{background:#fff7df;color:#805600}
    pre{max-width:420px;white-space:pre-wrap;overflow-wrap:anywhere}
    @media(max-width:600px){.card{padding:18px}th,td{padding:8px}.nav{font-size:.92rem}}
  </style>
</head>
<body>
<header>
  <nav class="nav" aria-label="Main navigation">
    <a href="index.php"><strong>Payment Practice</strong></a>
    <?php if ($showNavigation): ?>
      <?php if ($user): ?>
        <span><?= e($user['name']) ?> · <a href="dashboard.php">Dashboard</a> ·
          <form method="post" action="logout.php" style="display:inline">
            <?= csrf_field() ?><button type="submit" style="padding:6px 10px">Log out</button>
          </form>
        </span>
      <?php else: ?>
        <span><a href="login.php">Log in</a> · <a href="register.php">Create account</a></span>
      <?php endif; ?>
    <?php endif; ?>
  </nav>
</header>
<main>
  <?php if ($notice): ?>
    <div class="alert <?= e($notice['type'] ?? 'info') ?>" role="status"><?= e($notice['message'] ?? '') ?></div>
  <?php endif; ?>
<?php
}

function page_footer(): void
{
    ?>
</main>
</body>
</html>
<?php
}
