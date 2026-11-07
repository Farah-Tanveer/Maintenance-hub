<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';
require __DIR__ . '/../app/includes/slots.php';
require_role('customer');

$services = $conn->query(
    'SELECT id, name, icon FROM services WHERE is_active = 1 ORDER BY id'
)->fetch_all(MYSQLI_ASSOC);

// Price table for the live price preview in the browser.
$priceMap = [];
$res = $conn->query('SELECT service_id, property_type, size, price FROM packages WHERE is_active = 1');
foreach ($res as $row) {
    $priceMap[$row['service_id'] . '|' . $row['property_type'] . '|' . $row['size']] = (float) $row['price'];
}

$errors    = [];
$serviceId = (int) ($_GET['service'] ?? 0);
$type      = 'home';
$size      = 'small';
$date      = '';
$slot      = '';
$address   = '';
$notes     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $type      = $_POST['property_type'] ?? '';
    $size      = $_POST['size'] ?? '';
    $date      = $_POST['booking_date'] ?? '';
    $slot      = $_POST['slot'] ?? '';
    $address   = trim($_POST['address'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (!in_array($type, ['home', 'office'], true) || !in_array($size, ['small', 'medium', 'large'], true)) {
        $errors[] = 'Please choose a valid property type and size.';
    }
    if (!valid_booking_date($date)) {
        $errors[] = 'Please choose a date from tomorrow up to 60 days ahead.';
    }
    if (!in_array($slot, SLOT_TIMES, true)) {
        $errors[] = 'Please choose a time slot.';
    }
    if ($address === '' || mb_strlen($address) > 255) {
        $errors[] = 'Please enter the service address (up to 255 characters).';
    }
    if (mb_strlen($notes) > 500) {
        $errors[] = 'Notes can be up to 500 characters.';
    }

    $package = null;
    if (!$errors) {
        // The price always comes from the database, never from the form.
        $stmt = $conn->prepare(
            'SELECT id, price FROM packages
             WHERE service_id = ? AND property_type = ? AND size = ? AND is_active = 1'
        );
        $stmt->bind_param('iss', $serviceId, $type, $size);
        $stmt->execute();
        $package = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$package) {
            $errors[] = 'That package is not available. Please choose again.';
        }
    }

    if (!$errors) {
        foreach (get_slots($conn, $date) as $s) {
            if ($s['time'] === $slot && $s['free'] < 1) {
                $errors[] = 'Sorry, that time slot was just taken. Please pick another.';
            }
        }
    }

    if (!$errors) {
        $notesValue = ($notes === '') ? null : $notes;
        $price      = (string) $package['price'];
        $customerId = (int) $_SESSION['user_id'];
        $packageId  = (int) $package['id'];

        try {
            $stmt = $conn->prepare(
                'INSERT INTO bookings (customer_id, package_id, booking_date, slot_start, address, notes, price)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iisssss', $customerId, $packageId, $date, $slot, $address, $notesValue, $price);
            $stmt->execute();
            $stmt->close();
            redirect('/my-bookings.php?booked=1');
        } catch (mysqli_sql_exception $ex) {
            error_log('Booking failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Book a service';
require __DIR__ . '/../app/includes/header.php';
?>

<div class="auth-wrap">
    <div class="card auth-card book-card">
        <h1>Book a service</h1>
        <p class="lead">Choose a package, pick a date and a free time slot.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" action="/book.php" id="bookForm">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="field">
                <label for="service_id">Service</label>
                <select id="service_id" name="service_id" required>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $serviceId === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['icon'] . ' ' . $s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="property_type">Property type</label>
                    <select id="property_type" name="property_type">
                        <option value="home"   <?= $type === 'home' ? 'selected' : '' ?>>Home</option>
                        <option value="office" <?= $type === 'office' ? 'selected' : '' ?>>Office</option>
                    </select>
                </div>
                <div class="field">
                    <label for="size">Size</label>
                    <select id="size" name="size">
                        <option value="small"  <?= $size === 'small' ? 'selected' : '' ?>>Small</option>
                        <option value="medium" <?= $size === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="large"  <?= $size === 'large' ? 'selected' : '' ?>>Large</option>
                    </select>
                </div>
            </div>

            <div class="price-box">
                <span>Price for this package</span>
                <strong id="priceOut">Rs 0</strong>
            </div>

            <div class="field">
                <label for="booking_date">Date</label>
                <input type="date" id="booking_date" name="booking_date"
                       value="<?= e($date) ?>"
                       min="<?= date('Y-m-d', strtotime('tomorrow')) ?>"
                       max="<?= date('Y-m-d', strtotime('+60 days')) ?>" required>
            </div>

            <div class="field">
                <label>Time slot</label>
                <div class="slot-grid" id="slotGrid">
                    <p class="hint">Pick a date to see free time slots.</p>
                </div>
            </div>

            <div class="field">
                <label for="address">Service address</label>
                <input type="text" id="address" name="address" value="<?= e($address) ?>" maxlength="255" required>
            </div>

            <div class="field">
                <label for="notes">Notes (optional)</label>
                <textarea id="notes" name="notes" rows="3" maxlength="500"><?= e($notes) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Confirm booking</button>
        </form>
    </div>
</div>

<script>
const PRICES = <?= json_encode($priceMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const SELECTED_SLOT = <?= json_encode($slot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const serviceEl = document.getElementById('service_id');
const typeEl    = document.getElementById('property_type');
const sizeEl    = document.getElementById('size');
const dateEl    = document.getElementById('booking_date');
const priceOut  = document.getElementById('priceOut');
const slotGrid  = document.getElementById('slotGrid');

function updatePrice() {
    const key = serviceEl.value + '|' + typeEl.value + '|' + sizeEl.value;
    const price = PRICES[key];
    priceOut.textContent = price ? 'Rs ' + Number(price).toLocaleString('en-PK') : 'Not available';
}

function showMessage(text) {
    slotGrid.textContent = '';
    const p = document.createElement('p');
    p.className = 'hint';
    p.textContent = text;
    slotGrid.appendChild(p);
}

async function loadSlots() {
    if (!dateEl.value) {
        showMessage('Pick a date to see free time slots.');
        return;
    }
    showMessage('Loading slots...');
    try {
        const res = await fetch('/slots.php?date=' + encodeURIComponent(dateEl.value));
        const data = await res.json();
        if (!res.ok) {
            showMessage(data.error || 'Could not load slots.');
            return;
        }
        slotGrid.textContent = '';
        data.forEach(function (s) {
            const label = document.createElement('label');
            label.className = 'slot';

            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'slot';
            input.value = s.time;
            input.disabled = s.free < 1;
            input.checked = (s.time === SELECTED_SLOT && s.free > 0);

            const span = document.createElement('span');
            span.textContent = s.label + (s.free < 1 ? ' (full)' : '');

            label.appendChild(input);
            label.appendChild(span);
            slotGrid.appendChild(label);
        });
    } catch (err) {
        showMessage('Could not load slots. Please try again.');
    }
}

[serviceEl, typeEl, sizeEl].forEach(function (el) { el.addEventListener('change', updatePrice); });
dateEl.addEventListener('change', loadSlots);

updatePrice();
if (dateEl.value) { loadSlots(); }
</script>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
