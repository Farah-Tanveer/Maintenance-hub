<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $workerId  = (int) ($_POST['worker_id'] ?? 0);

    if (!csrf_check() || $bookingId < 1 || $workerId < 1) {
        redirect('/admin-bookings.php?msg=invalid');
    }

    // The booking must still be open (requested or confirmed).
    $stmt = $conn->prepare(
        "SELECT booking_date, slot_start FROM bookings
         WHERE id = ? AND status IN ('requested', 'confirmed')"
    );
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$booking) {
        redirect('/admin-bookings.php?msg=notfound');
    }

    // The chosen user must be an active worker.
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'worker' AND is_active = 1");
    $stmt->bind_param('i', $workerId);
    $stmt->execute();
    $worker = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$worker) {
        redirect('/admin-bookings.php?msg=noworker');
    }

    // The worker must not already have a job in the same slot.
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM bookings
         WHERE worker_id = ? AND booking_date = ? AND slot_start = ?
           AND status <> 'cancelled' AND id <> ?"
    );
    $stmt->bind_param('issi', $workerId, $booking['booking_date'], $booking['slot_start'], $bookingId);
    $stmt->execute();
    $clash = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    if ($clash > 0) {
        redirect('/admin-bookings.php?msg=conflict');
    }

    $stmt = $conn->prepare(
        "UPDATE bookings SET worker_id = ?, status = 'confirmed'
         WHERE id = ? AND status IN ('requested', 'confirmed')"
    );
    $stmt->bind_param('ii', $workerId, $bookingId);
    $stmt->execute();
    $stmt->close();
    redirect('/admin-bookings.php?msg=assigned');
}

$workers = $conn->query(
    "SELECT id, full_name FROM users WHERE role = 'worker' AND is_active = 1 ORDER BY full_name"
)->fetch_all(MYSQLI_ASSOC);

$bookings = $conn->query(
    "SELECT b.id, b.booking_date, TIME_FORMAT(b.slot_start, '%l:%i %p') AS slot,
            b.address, b.notes, b.price, b.status, b.worker_id,
            c.full_name AS customer, c.phone AS customer_phone,
            w.full_name AS worker,
            s.name AS service, s.icon, p.property_type, p.size
     FROM bookings b
     JOIN users c ON c.id = b.customer_id
     LEFT JOIN users w ON w.id = b.worker_id
     JOIN packages p ON p.id = b.package_id
     JOIN services s ON s.id = p.service_id
     ORDER BY b.booking_date DESC, b.slot_start DESC"
)->fetch_all(MYSQLI_ASSOC);

$statusLabels = [
    'requested'   => 'Requested',
    'confirmed'   => 'Confirmed',
    'in_progress' => 'In progress',
    'completed'   => 'Completed',
    'cancelled'   => 'Cancelled',
];

$messages = [
    'assigned' => ['success', 'Worker assigned and booking confirmed.'],
    'invalid'  => ['error', 'Something was missing. Please try again.'],
    'notfound' => ['error', 'That booking can no longer be changed.'],
    'noworker' => ['error', 'That worker could not be found.'],
    'conflict' => ['error', 'That worker already has a job in the same time slot.'],
];
$msg = $messages[$_GET['msg'] ?? ''] ?? null;

$pageTitle = 'All bookings';
require __DIR__ . '/../app/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>All bookings</h2>
        <p class="lead">Review requests and assign a worker to each one.</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div>
        <?php endif; ?>

        <?php if (!$bookings): ?>
            <div class="card empty-state"><h3>No bookings yet</h3><p>Customer bookings will appear here.</p></div>
        <?php else: ?>
            <div class="booking-list">
                <?php foreach ($bookings as $b): ?>
                    <div class="booking">
                        <div>
                            <h3><?= e($b['icon'] . ' ' . $b['service']) ?></h3>
                            <p class="meta"><?= e(date('D, j M Y', strtotime($b['booking_date']))) ?> at <?= e($b['slot']) ?></p>
                            <p><?= e(ucfirst($b['property_type'])) ?>, <?= e($b['size']) ?> &middot; Rs <?= number_format((float) $b['price']) ?></p>
                            <p>Customer: <?= e($b['customer']) ?><?= $b['customer_phone'] ? ' (' . e($b['customer_phone']) . ')' : '' ?></p>
                            <p><?= e($b['address']) ?></p>
                            <?php if ($b['notes']): ?><p>Notes: <?= e($b['notes']) ?></p><?php endif; ?>
                        </div>
                        <div class="booking-side">
                            <span class="badge badge-<?= e($b['status']) ?>"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span>

                            <?php if (in_array($b['status'], ['requested', 'confirmed'], true)): ?>
                                <?php if (!$workers): ?>
                                    <p class="hint">Add a worker first.</p>
                                <?php else: ?>
                                    <form method="post" action="/admin-bookings.php" class="assign-form">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                        <select name="worker_id" required>
                                            <option value="">Choose worker</option>
                                            <?php foreach ($workers as $w): ?>
                                                <option value="<?= (int) $w['id'] ?>" <?= (int) $b['worker_id'] === (int) $w['id'] ? 'selected' : '' ?>>
                                                    <?= e($w['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-small">
                                            <?= $b['worker_id'] ? 'Reassign' : 'Assign' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ($b['worker']): ?>
                                <p class="meta">Worker: <?= e($b['worker']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
