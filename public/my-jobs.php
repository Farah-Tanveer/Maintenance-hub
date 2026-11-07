<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';
require_role('worker');

$workerId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    if (!csrf_check() || $bookingId < 1) {
        redirect('/my-jobs.php?msg=failed');
    }

    // Each action may only move a job one step forward.
    $steps = [
        'start'    => ['from' => 'confirmed',   'to' => 'in_progress'],
        'complete' => ['from' => 'in_progress', 'to' => 'completed'],
    ];
    if (!isset($steps[$action])) {
        redirect('/my-jobs.php?msg=failed');
    }

    $from = $steps[$action]['from'];
    $to   = $steps[$action]['to'];

    // worker_id = ? means a worker can only update their own jobs.
    $stmt = $conn->prepare(
        'UPDATE bookings SET status = ? WHERE id = ? AND worker_id = ? AND status = ?'
    );
    $stmt->bind_param('siis', $to, $bookingId, $workerId, $from);
    $stmt->execute();
    $changed = $stmt->affected_rows;
    $stmt->close();

    redirect('/my-jobs.php?msg=' . ($changed > 0 ? $action : 'failed'));
}

$stmt = $conn->prepare(
    "SELECT b.id, b.booking_date, TIME_FORMAT(b.slot_start, '%l:%i %p') AS slot,
            b.address, b.notes, b.status,
            c.full_name AS customer, c.phone AS customer_phone,
            s.name AS service, s.icon, p.property_type, p.size
     FROM bookings b
     JOIN users c ON c.id = b.customer_id
     JOIN packages p ON p.id = b.package_id
     JOIN services s ON s.id = p.service_id
     WHERE b.worker_id = ?
     ORDER BY b.booking_date ASC, b.slot_start ASC"
);
$stmt->bind_param('i', $workerId);
$stmt->execute();
$all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Active jobs first (soonest at the top), finished and cancelled ones below.
$active = [];
$past   = [];
foreach ($all as $job) {
    if (in_array($job['status'], ['confirmed', 'in_progress'], true)) {
        $active[] = $job;
    } else {
        $past[] = $job;
    }
}
$past = array_reverse($past);

$statusLabels = [
    'requested'   => 'Requested',
    'confirmed'   => 'Confirmed',
    'in_progress' => 'In progress',
    'completed'   => 'Completed',
    'cancelled'   => 'Cancelled',
];

$messages = [
    'start'    => ['success', 'Job started. Good luck!'],
    'complete' => ['success', 'Job marked as completed. Nice work.'],
    'failed'   => ['error', 'That job could not be updated.'],
];
$msg = $messages[$_GET['msg'] ?? ''] ?? null;

// Prints one job card. Defined here because only this page uses it.
function render_job(array $b, array $statusLabels): void
{
    ?>
    <div class="booking">
        <div>
            <h3><?= e($b['icon'] . ' ' . $b['service']) ?></h3>
            <p class="meta"><?= e(date('D, j M Y', strtotime($b['booking_date']))) ?> at <?= e($b['slot']) ?></p>
            <p><?= e(ucfirst($b['property_type'])) ?>, <?= e($b['size']) ?></p>
            <p>Customer: <?= e($b['customer']) ?><?= $b['customer_phone'] ? ' (' . e($b['customer_phone']) . ')' : '' ?></p>
            <p><?= e($b['address']) ?></p>
            <?php if ($b['notes']): ?><p>Notes: <?= e($b['notes']) ?></p><?php endif; ?>
        </div>
        <div class="booking-side">
            <span class="badge badge-<?= e($b['status']) ?>"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span>

            <?php if ($b['status'] === 'confirmed'): ?>
                <form method="post" action="/my-jobs.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                    <input type="hidden" name="action" value="start">
                    <button type="submit" class="btn btn-primary btn-small">Start job</button>
                </form>
            <?php elseif ($b['status'] === 'in_progress'): ?>
                <form method="post" action="/my-jobs.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn btn-primary btn-small">Mark completed</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

$pageTitle = 'My jobs';
require __DIR__ . '/../app/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>My jobs</h2>
        <p class="lead">Jobs assigned to you. Update the progress as you work.</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div>
        <?php endif; ?>

        <?php if (!$all): ?>
            <div class="card empty-state">
                <h3>No jobs yet</h3>
                <p>When an admin assigns you a booking, it will show up here.</p>
            </div>
        <?php else: ?>
            <h3 class="subhead" style="margin-top:0;">Active jobs</h3>
            <?php if (!$active): ?>
                <p class="hint">Nothing active right now.</p>
            <?php else: ?>
                <div class="booking-list">
                    <?php foreach ($active as $job) { render_job($job, $statusLabels); } ?>
                </div>
            <?php endif; ?>

            <?php if ($past): ?>
                <h3 class="subhead">Finished and cancelled</h3>
                <div class="booking-list">
                    <?php foreach ($past as $job) { render_job($job, $statusLabels); } ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
