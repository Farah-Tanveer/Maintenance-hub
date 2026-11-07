<?php
require __DIR__ . '/../app/includes/auth.php';
require_login();

$role = $_SESSION['role'];
$pageTitle = 'Dashboard';
require __DIR__ . '/../app/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>Welcome, <?= e($_SESSION['name']) ?></h2>
        <p class="lead">You are logged in as <strong><?= e($role) ?></strong>.</p>

        <div class="grid">
            <?php if ($role === 'admin'): ?>
                <div class="card"><div class="icon">🛠️</div><h3>Services and packages</h3><p>Coming soon: manage the catalog.</p></div>
                <div class="card"><div class="icon">👷</div><h3>Workers</h3><p>Coming soon: add and manage workers.</p></div>
                <div class="card"><div class="icon">📋</div><h3>Bookings</h3><p>Coming soon: assign and track bookings.</p></div>
                <div class="card"><div class="icon">📈</div><h3>Reports</h3><p>Coming soon: revenue and top rated workers.</p></div>
            <?php elseif ($role === 'worker'): ?>
                <div class="card"><div class="icon">📋</div><h3>My jobs</h3><p>Coming soon: your assigned jobs and progress updates.</p></div>
            <?php else: ?>
                <a class="card card-link" href="/book.php"><div class="icon">🗓️</div><h3>Book a service</h3><p>Pick a package, a date and a free time slot.</p></a>
                <a class="card card-link" href="/my-bookings.php"><div class="icon">📋</div><h3>My bookings</h3><p>Track status and cancel if your plans change.</p></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
