<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId   = (int) ($_POST['service_id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isActive    = isset($_POST['is_active']) ? 1 : 0;
    $prices      = $_POST['prices'] ?? [];

    if (!csrf_check() || $serviceId < 1 || !is_array($prices)) {
        redirect('/admin-services.php?msg=failed');
    }
    if ($name === '' || mb_strlen($name) > 100 || $description === '' || mb_strlen($description) > 255) {
        redirect('/admin-services.php?msg=badtext');
    }

    // Only packages that belong to this service may be changed.
    $stmt = $conn->prepare('SELECT id FROM packages WHERE service_id = ?');
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    $ownIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $stmt->close();
    if (!$ownIds) {
        redirect('/admin-services.php?msg=failed');
    }

    $clean = [];
    foreach ($ownIds as $pid) {
        $raw = $prices[$pid] ?? null;
        if ($raw === null || !is_numeric($raw) || (float) $raw <= 0 || (float) $raw > 1000000) {
            redirect('/admin-services.php?msg=badprice');
        }
        $clean[(int) $pid] = number_format((float) $raw, 2, '.', '');
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare('UPDATE services SET name = ?, description = ?, is_active = ? WHERE id = ?');
        $stmt->bind_param('ssii', $name, $description, $isActive, $serviceId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('UPDATE packages SET price = ? WHERE id = ? AND service_id = ?');
        foreach ($clean as $pid => $price) {
            $stmt->bind_param('sii', $price, $pid, $serviceId);
            $stmt->execute();
        }
        $stmt->close();

        // Keep the base price in step with the cheapest home package.
        $stmt = $conn->prepare(
            "UPDATE services SET base_price =
                (SELECT price FROM packages
                 WHERE service_id = ? AND property_type = 'home' AND size = 'small')
             WHERE id = ?"
        );
        $stmt->bind_param('ii', $serviceId, $serviceId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        redirect('/admin-services.php?msg=saved');
    } catch (mysqli_sql_exception $ex) {
        $conn->rollback();
        error_log('Catalog update failed: ' . $ex->getMessage());
        redirect('/admin-services.php?msg=failed');
    }
}

$services = $conn->query(
    'SELECT id, name, description, icon, is_active FROM services ORDER BY id'
)->fetch_all(MYSQLI_ASSOC);

$packages = [];
$res = $conn->query(
    "SELECT id, service_id, property_type, size, price FROM packages
     ORDER BY service_id, property_type, FIELD(size, 'small', 'medium', 'large')"
);
foreach ($res as $row) {
    $packages[$row['service_id']][] = $row;
}

$messages = [
    'saved'    => ['success', 'Changes saved.'],
    'failed'   => ['error', 'Something went wrong. Nothing was changed.'],
    'badtext'  => ['error', 'Name and description are required (name up to 100, description up to 255 characters).'],
    'badprice' => ['error', 'Every price must be a number above 0 and up to 1,000,000.'],
];
$msg = $messages[$_GET['msg'] ?? ''] ?? null;

$pageTitle = 'Services and packages';
require __DIR__ . '/../app/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>Services and packages</h2>
        <p class="lead">Edit what customers see and what they pay. Hidden services disappear from the home page and the booking form.</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div>
        <?php endif; ?>

        <?php foreach ($services as $s): ?>
            <div class="card service-admin">
                <h3><?= e($s['icon'] . ' ' . $s['name']) ?></h3>

                <form method="post" action="/admin-services.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="service_id" value="<?= (int) $s['id'] ?>">

                    <div class="field">
                        <label for="name<?= (int) $s['id'] ?>">Name</label>
                        <input type="text" id="name<?= (int) $s['id'] ?>" name="name" value="<?= e($s['name']) ?>" maxlength="100" required>
                    </div>
                    <div class="field">
                        <label for="desc<?= (int) $s['id'] ?>">Description</label>
                        <input type="text" id="desc<?= (int) $s['id'] ?>" name="description" value="<?= e($s['description']) ?>" maxlength="255" required>
                    </div>

                    <div class="pkg-grid">
                        <?php $lastType = ''; ?>
                        <?php foreach ($packages[$s['id']] ?? [] as $p): ?>
                            <?php if ($p['property_type'] !== $lastType): ?>
                                <div class="label"><?= e(ucfirst($p['property_type'])) ?></div>
                                <?php $lastType = $p['property_type']; ?>
                            <?php endif; ?>
                            <div>
                                <label for="p<?= (int) $p['id'] ?>"><?= e(ucfirst($p['size'])) ?> (Rs)</label>
                                <input type="number" id="p<?= (int) $p['id'] ?>" name="prices[<?= (int) $p['id'] ?>]"
                                       value="<?= e((string) (int) $p['price']) ?>" min="1" max="1000000" step="1" required>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="check-row">
                        <input type="checkbox" id="act<?= (int) $s['id'] ?>" name="is_active" value="1" <?= $s['is_active'] ? 'checked' : '' ?>>
                        <label for="act<?= (int) $s['id'] ?>">Visible to customers</label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-small">Save changes</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
