<?php
require __DIR__ . '/../app/includes/auth.php';
require_login();

$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Maintenance Hub</title>
</head>
<body>
    <h1>Welcome, <?= e($_SESSION['name']) ?></h1>
    <p>You are logged in as: <strong><?= e($role) ?></strong></p>

    <?php if ($role === 'admin'): ?>
        <p>Admin area will go here: services, workers, bookings, reports.</p>
    <?php elseif ($role === 'worker'): ?>
        <p>Worker area will go here: your assigned jobs.</p>
    <?php else: ?>
        <p>Customer area will go here: book a service, see your bookings.</p>
    <?php endif; ?>

    <form method="post" action="/logout.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit">Log out</button>
    </form>
</body>
</html>
