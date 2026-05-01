<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — <?= APP_SHORT ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-card">
      <div class="login-logo">
        <div class="login-logo-icon">F</div>
        <h1><?= APP_SHORT ?></h1>
        <p><?= APP_NAME ?></p>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>
      <?= flash_html() ?>

      <form method="POST" action="index.php?page=login">
        <?= csrf_field() ?>
        <div class="form-group mb-2">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required
                 autocomplete="username" placeholder="Enter your username">
        </div>
        <div class="form-group mb-2">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required
                 autocomplete="current-password" placeholder="Enter your password">
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-1">Sign In</button>
      </form>

      <p class="text-small text-muted mt-2" style="text-align:center">
        Default admin: <code>admin</code> / <code>admin123</code>
      </p>
      <?php if (isset($_SESSION['session_test'])): ?>
        <p class="text-small text-green" style="text-align:center; font-size: 10px;">Session Active (<?= $_SESSION['session_test'] ?>)</p>
      <?php else: ?>
        <p class="text-small text-red" style="text-align:center; font-size: 10px;">Session Inactive (Check Server)</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
