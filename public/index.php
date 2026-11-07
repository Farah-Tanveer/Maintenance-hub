<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';

$services = $conn->query(
    'SELECT s.id, s.name, s.description, s.icon, MIN(p.price) AS from_price
     FROM services s
     JOIN packages p ON p.service_id = s.id AND p.is_active = 1
     WHERE s.is_active = 1
     GROUP BY s.id, s.name, s.description, s.icon
     ORDER BY s.id'
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Home';
require __DIR__ . '/../app/includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Trusted maintenance for your home and office</h1>
        <p>Book cleaning, plumbing, electrical and AC servicing online. Pick a package, choose a time slot, and a verified worker shows up.</p>
        <a href="/register.php" class="btn btn-primary">Get started</a>
        <a href="#services" class="btn btn-outline">See services</a>
    </div>
</section>

<section class="section" id="services">
    <div class="container">
        <h2>Our services</h2>
        <p class="lead">Packages priced by property type and size.</p>
        <div class="grid">
            <?php foreach ($services as $s): ?>
                <div class="card">
                    <div class="icon"><?= e($s['icon']) ?></div>
                    <h3><?= e($s['name']) ?></h3>
                    <p><?= e($s['description']) ?></p>
                    <p class="price">From Rs <?= number_format((float) $s['from_price']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="background:#fff;">
    <div class="container">
        <h2>How it works</h2>
        <p class="lead">From request to done in three steps.</p>
        <div class="grid">
            <div class="card"><div class="step-number">1</div><h3>Choose a package</h3><p>Pick a service and the package that fits your property.</p></div>
            <div class="card"><div class="step-number">2</div><h3>Pick a time slot</h3><p>See free slots and book the one that suits you.</p></div>
            <div class="card"><div class="step-number">3</div><h3>Track and review</h3><p>Follow your booking status and rate the worker afterwards.</p></div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
