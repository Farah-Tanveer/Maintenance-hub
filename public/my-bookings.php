<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';
require_role('customer');

$customerId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $action    = $_POST['action'] ?? 'cancel';

    if (!csrf_check() || $bookingId < 1) {
        redirect('/my-bookings.php?msg=failed');
    }

    if ($action === 'review') {
        $rating  = (int) ($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5 || mb_strlen($comment) > 500) {
            redirect('/my-bookings.php?msg=badreview');
        }

        // Only your own completed booking, with a worker, can be reviewed.
        $stmt = $conn->prepare(
            "SELECT worker_id FROM bookings
             WHERE id = ? AND customer_id = ? AND status = 'completed' AND worker_id IS NOT NULL"
        );
        $stmt->bind_param('ii', $bookingId, $customerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            redirect('/my-bookings.php?msg=failed');
        }

        $workerId     = (int) $row['worker_id'];
        $commentValue = ($comment === '') ? null : $comment;

        try {
            $stmt = $conn->prepare(
                'INSERT INTO reviews (booking_id, customer_id, worker_id, rating, comment)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iiiis', $bookingId, $customerId, $workerId, $rating, $commentValue);
            $stmt->execute();
            $stmt->close();
            redirect('/my-bookings.php?msg=reviewed');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) {   // this booking already has a review
                redirect('/my-bookings.php?msg=reviewexists');
            }
            error_log('Review failed: ' . $ex->getMessage());
            redirect('/my-bookings.php?msg=failed');
        }
    }

    // Default action: cancel. You can only cancel your own bookings,
    // and finished jobs cannot be cancelled.
    $stmt = $conn->prepare(
        "UPDATE bookings SET status = 'cancelled'
         WHERE id = ? AND customer_id = ? AND status IN ('requested', 'confirmed')"
    );
    $stmt->bind_param('ii', $bookingId, $customerId);
    $stmt->execute();
    $changed = $stmt->affected_rows;
    $stmt->close();
    redirect('/my-bookings.php?msg=' . ($changed > 0 ? 'cancelled' : 'failed'));
}

$stmt = $conn->prepare(
    "SELECT b.id, b.booking_date, TIME_FORMAT(b.slot_start, '%l:%i %p') AS slot,
            b.address, b.price, b.status,
            s.name AS service, s.icon, p.property_type, p.size,
            w.full_name AS worker,
            r.rating, r.comment AS review_comment
     FROM bookings b
     JOIN packages p ON p.id = b.package_id
     JOIN services s ON s.id = p.service_id
     LEFT JOIN users w ON w.id = b.worker_id
     LEFT JOIN reviews r ON r.booking_id = b.id
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

$messages = [
    'cancelled'    => ['success', 'Your booking was cancelled.'],
    'reviewed'     => ['success', 'Thank you! Your review was saved.'],
    'reviewexists' => ['error', 'You already reviewed this booking.'],
    'badreview'    => ['error', 'Please choose a rating from 1 to 5 and keep your comment under 500 characters.'],
    'failed'       => ['error', 'That action could not be completed.'],
];
$msg = $messages[$_GET['msg'] ?? ''] ?? null;

$pageTitle = 'My bookings';
require __DIR__ . '/../app/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>My bookings</h2>
        <p class="lead">Track the status of your requests and review finished jobs.</p>

        <?php if (isset($_GET['booked'])): ?>
            <div class="alert alert-success">Booking requested. We will confirm it shortly.</div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="alert alert-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div>
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
                            <?php if ($b['worker']): ?>
                                <p>Worker: <?= e($b['worker']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="booking-side">
                            <span class="badge badge-<?= e($b['status']) ?>"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span>
                            <span class="amount">Rs <?= number_format((float) $b['price']) ?></span>
                            <?php if (in_array($b['status'], ['requested', 'confirmed'], true)): ?>
                                <form method="post" action="/my-bookings.php"
                                      onsubmit="return confirm('Cancel this booking?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-danger">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if ($b['status'] === 'completed' && $b['worker']): ?>
                            <div class="review-box">
                                <?php if ($b['rating'] !== null): ?>
                                    <strong>Your review</strong>
                                    <div class="stars" aria-label="<?= (int) $b['rating'] ?> out of 5 stars">
                                        <?= str_repeat('★', (int) $b['rating']) . str_repeat('☆', 5 - (int) $b['rating']) ?>
                                    </div>
                                    <?php if ($b['review_comment']): ?>
                                        <p class="review-text"><?= e($b['review_comment']) ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <strong>How did <?= e($b['worker']) ?> do?</strong>
                                    <form method="post" action="/my-bookings.php">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                        <input type="hidden" name="action" value="review">
                                        <select name="rating" required>
                                            <option value="">Choose a rating</option>
                                            <option value="5">5 stars, excellent</option>
                                            <option value="4">4 stars, good</option>
                                            <option value="3">3 stars, okay</option>
                                            <option value="2">2 stars, poor</option>
                                            <option value="1">1 star, very poor</option>
                                        </select>
                                        <textarea name="comment" rows="2" maxlength="500" placeholder="Add a comment (optional)"></textarea>
                                        <button type="submit" class="btn btn-primary btn-small">Submit review</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p style="margin-top:24px;"><a href="/book.php" class="btn btn-primary">Book another service</a></p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
