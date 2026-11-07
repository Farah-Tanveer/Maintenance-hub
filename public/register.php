<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

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
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Please enter your full name (up to 100 characters).';
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
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
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
        $role       = 'customer';
        $phoneValue = ($phone === '') ? null : $phone;

        try {
            $stmt = $conn->prepare(
                'INSERT INTO users (full_name, email, password_hash, phone, role)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssss', $name, $email, $hash, $phoneValue, $role);
            $stmt->execute();
            $stmt->close();
            redirect('/login.php?registered=1');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) {
                $errors[] = 'An account with this email already exists.';
            } else {
                error_log('Register failed: ' . $ex->getMessage());
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign up';
require __DIR__ . '/../app/includes/header.php';
?>

<div class="auth-wrap">
    <div class="card auth-card">
        <h1>Create your account</h1>
        <p class="lead">It takes less than a minute.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" action="/register.php">
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
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Sign up</button>
        </form>

        <p style="margin-top:18px;">Already have an account? <a href="/login.php">Log in</a></p>
    </div>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
