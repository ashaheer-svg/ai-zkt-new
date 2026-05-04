<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Financial Assistance Management System — FAMS">
<title><?= e($pageTitle ?? 'FAMS') ?> — <?= APP_SHORT ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= APP_DEPLOY_VERSION ?>">
</head>
<body>
<div class="app-wrapper">
  <!-- Mobile top bar -->
  <header class="topbar">
    <button class="hamburger" id="menuToggle" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
    <div class="topbar-brand"><?= APP_SHORT ?></div>
    <div class="topbar-user">
      <span class="user-pill"><?= e($_SESSION['user_name'] ?? '') ?></span>
    </div>
  </header>
