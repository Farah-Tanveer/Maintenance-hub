<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';
require_role('admin');

// Headline numbers.
$totals = $conn->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'completed'), 0) AS completed,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN price ELSE 0 END), 0) AS revenue
     FROM bookings"
)->fetch_assoc();

$avgRow = $conn->query('SELECT AVG(rating) AS avg_rating, COUNT(*) AS reviews FROM reviews')->fetch_assoc();

// Bookings per status.
$statusLabels = [
    'requested'   => 'Requested',
    'confirmed'   => 'Confirmed',
    'in_progress' => 'In progress',
    'completed'   => 'Completed',
    'cancelled'   => 'Cancelled',
];
$counts = array_fill_keys(array_keys($statusLabels), 0);
foreach ($conn->query('SELECT status, COUNT(*) AS c FROM bookings GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['c'];
}
$maxCount = max(1, max($counts));

// Revenue per service, completed jobs only.
$perService = $conn->query(
    "SELECT s.icon, s.name, COUNT(b.id) AS jobs, COALESCE(SUM(b.price), 0) AS revenue
     FROM services s
     LEFT JOIN packages p ON p.service_id = s.id
     LEFT JOIN bookings b ON b.package_id = p.id AND b.status = 'completed'
     GROUP BY s.id, s.icon, s.name
     ORDER BY revenue DESC, s.id"
)->fetch_all(MYSQLI_ASSOC);

// Workers ranked by average rating, then by how many reviews back it up.
$workers = $conn->query(
    "SELECT u.full_name,
            (SELECT COUNT(*) FROM bookings b WHERE b.worker_id = u.id AND b.status = 'completed') AS jobs,
            AVG(r.rating) AS avg_rating,
            COUNT(r.id) AS reviews
     FROM users u
     LEFT JOIN reviews r ON r.worker_id = u.id
     WHERE u.role = 'worker'
     GROUP BY u.id, u.full_name
     ORDER BY avg_rating IS NULL, avg_rating DESC, reviews DESC, u.full_name"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Reports';
require __DIR__ . '/../app/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>Reports</h2>
        <p class="lead">A quick view of how the business is doing.</p>

        <div class="stat-grid">
            <div class="stat"><div class="num">Rs <?= number_format((float) $totals['revenue']) ?></div><div class="label">Revenue from completed jobs</div></div>
            <div class="stat"><div class="num"><?= (int) $totals['total'] ?></div><div class="label">Total bookings</div></div>
            <div class="stat"><div class="num"><?= (int) $totals['completed'] ?></div><div class="label">Completed jobs</div></div>
            <div class="stat">
                <div class="num"><?= $avgRow['avg_rating'] !== null ? number_format((float) $avgRow['avg_rating'], 1) . ' / 5' : 'No ratings' ?></div>
                <div class="label">Average rating (<?= (int) $avgRow['reviews'] ?> reviews)</div>
            </div>
        </div>

        <div class="report-grid">
            <div class="card">
                <h3>Bookings by status</h3>
                <?php foreach ($statusLabels as $key => $label): ?>
                    <div class="bar-row">
                        <span><?= e($label) ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?= (int) round($counts[$key] / $maxCount * 100) ?>%"></div></div>
                        <strong><?= (int) $counts[$key] ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h3>Revenue by service</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Service</th><th>Jobs</th><th>Revenue</th></tr></thead>
                        <tbody>
                            <?php foreach ($perService as $row): ?>
                                <tr>
                                    <td><?= e($row['icon'] . ' ' . $row['name']) ?></td>
                                    <td><?= (int) $row['jobs'] ?></td>
                                    <td>Rs <?= number_format((float) $row['revenue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <h3 class="subhead" style="margin-top:0;">Top rated workers</h3>
        <?php if (!$workers): ?>
            <div class="card empty-state"><h3>No workers yet</h3><p>Add workers from the Workers page.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Worker</th><th>Completed jobs</th><th>Rating</th><th>Reviews</th></tr></thead>
                    <tbody>
                        <?php foreach ($workers as $w): ?>
                            <tr>
                                <td><?= e($w['full_name']) ?></td>
                                <td><?= (int) $w['jobs'] ?></td>
                                <td>
                                    <?php if ($w['avg_rating'] !== null): ?>
                                        <span class="stars"><?= str_repeat('★', (int) round((float) $w['avg_rating'])) . str_repeat('☆', 5 - (int) round((float) $w['avg_rating'])) ?></span>
                                        <?= number_format((float) $w['avg_rating'], 1) ?>
                                    <?php else: ?>
                                        <span class="hint">Not rated yet</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $w['reviews'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
