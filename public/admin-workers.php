<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';
require_role('admin');

$errors = [];
$name = $email = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $name     = trim($_POST['full_name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Please enter the worker\'s full name (up to 100 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        }
        $stmt->close();
    }

    if (!$errors) {
        $hash       = password_hash($password, PASSWORD_DEFAULT);
        $role       = 'worker';
        $phoneValue = ($phone === '') ? null : $phone;

        try {
            $stmt = $conn->prepare(
                'INSERT INTO users (full_name, email, password_hash, phone, role)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssss', $name, $email, $hash, $phoneValue, $role);
            $stmt->execute();
            $stmt->close();
            redirect('/admin-workers.php?created=1');
        } catch (mysqli_sql_exception $ex) {
            error_log('Create worker failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$workers = $conn->query(
    "SELECT id, full_name, email, phone, created_at
     FROM users WHERE role = 'worker' ORDER BY id DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Workers';
require __DIR__ . '/../app/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>Workers</h2>
        <p class="lead">Create worker accounts and see who is on your team.</p>

        <?php if (isset($_GET['created'])): ?>
            <div class="alert alert-success">Worker account created.</div>
        <?php endif; ?>

        <div class="two-col">
            <div class="card">
                <h3 style="margin-top:0;">Add a worker</h3>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="post" action="/admin-workers.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <div class="field">
                        <label for="full_name">Full name</label>
                        <input type="text" id="full_name" name="full_name" value="<?= e($name) ?>" required>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
                    </div>
                    <div class="field">
                        <label for="phone">Phone (optional)</label>
                        <input type="text" id="phone" name="phone" value="<?= e($phone) ?>">
                    </div>
                    <div class="field">
                        <label for="password">Starting password</label>
                        <input type="password" id="password" name="password" required>
                        <p class="hint">At least 8 characters. Share it with the worker privately.</p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Create worker</button>
                </form>
            </div>

            <div class="table-wrap">
                <?php if (!$workers): ?>
                    <div class="card empty-state"><h3>No workers yet</h3><p>Add your first worker using the form.</p></div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>Phone</th><th>Added</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workers as $w): ?>
                                <tr>
                                    <td><?= e($w['full_name']) ?></td>
                                    <td><?= e($w['email']) ?></td>
                                    <td><?= e($w['phone'] ?? '') ?></td>
                                    <td><?= e(date('j M Y', strtotime($w['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
