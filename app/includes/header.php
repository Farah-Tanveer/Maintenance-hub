<?php
require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? 'Maintenance Hub';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Maintenance Hub</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="/">Maintenance<span>Hub</span></a>
        <nav class="nav-links">
            <a href="/">Home</a>
            <a href="/#services">Services</a>
            <?php if (is_logged_in()): ?>
                <a href="/dashboard.php">Dashboard</a>
                <form method="post" action="/logout.php" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="btn btn-outline">Log out</button>
                </form>
            <?php else: ?>
                <a href="/login.php">Log in</a>
                <a href="/register.php" class="btn btn-primary">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
