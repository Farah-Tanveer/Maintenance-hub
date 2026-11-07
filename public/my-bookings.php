<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';
require_role('customer');

$customerId = (int) $_SESSION['user_id'];

// Cancel a booking (POST only, with CSRF token).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);

    if (csrf_check() && $bookingId > 0) {
        // The customer_id check means you can only cancel your own bookings,
        // and the status check means finished jobs cannot be cancelled.
        $stmt = $conn->prepare(
            "UPDATE bookings SET status = 'cancelled'
             WHERE id = ? AND customer_id = ? AND status IN ('requested', 'confirmed')"
        );
        $stmt->bind_param('ii', $bookingId, $customerId);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        redirect('/my-bookings.php?' . ($changed > 0 ? 'cancelled=1' : 'failed=1'));
    }
    redirect('/my-bookings.php?failed=1');
}

$stmt = $conn->prepare(
    "SELECT b.id, b.booking_date, TIME_FORMAT(b.slot_start, '%l:%i %p') AS slot,
            b.address, b.price, b.status,
            s.name AS service, s.icon, p.property_type, p.size
     FROM bookings b
     JOIN packages p ON p.id = b.package_id
     JOIN services s ON s.id = p.service_id
     WHERE b.customer_id = ?
     ORDER BY b.booking_date DESC, b.slot_start DESC"
);
$stmt->bind_param('i', $customerId);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$statusLabels = [
    'requested'   => 'Requested',
    'confirmed'   => 'Confirmed',
    'in_progress' => 'In progress',
    'completed'   => 'Completed',
    'cancelled'   => 'Cancelled',
];

$pageTitle = 'My bookings';
require __DIR__ . '/../app/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>My bookings</h2>
        <p class="lead">Track the status of your requests.</p>

        <?php if (isset($_GET['booked'])): ?>
            <div class="alert alert-success">Booking requested. We will confirm it shortly.</div>
        <?php endif; ?>
        <?php if (isset($_GET['cancelled'])): ?>
            <div class="alert alert-success">Your booking was cancelled.</div>
        <?php endif; ?>
        <?php if (isset($_GET['failed'])): ?>
            <div class="alert alert-error">That booking could not be cancelled.</div>
        <?php endif; ?>

        <?php if (!$bookings): ?>
            <div class="card empty-state">
                <h3>No bookings yet</h3>
                <p>Book your first service and it will show up here.</p>
                <p style="margin-top:16px;"><a href="/book.php" class="btn btn-primary">Book a service</a></p>
            </div>
        <?php else: ?>
            <div class="booking-list">
                <?php foreach ($bookings as $b): ?>
                    <div class="booking">
                        <div>
                            <h3><?= e($b['icon'] . ' ' . $b['service']) ?></h3>
                            <p class="meta">
                                <?= e(date('D, j M Y', strtotime($b['booking_date']))) ?> at <?= e($b['slot']) ?>
                            </p>
                            <p><?= e(ucfirst($b['property_type'])) ?>, <?= e($b['size']) ?></p>
                            <p><?= e($b['address']) ?></p>
                        </div>
                        <div class="booking-side">
                            <span class="badge badge-<?= e($b['status']) ?>"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span>
                            <span class="amount">Rs <?= number_format((float) $b['price']) ?></span>
                            <?php if (in_array($b['status'], ['requested', 'confirmed'], true)): ?>
                                <form method="post" action="/my-bookings.php"
                                      onsubmit="return confirm('Cancel this booking?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                    <button type="submit" class="btn btn-danger">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p style="margin-top:24px;"><a href="/book.php" class="btn btn-primary">Book another service</a></p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
