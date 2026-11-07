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
        $hash  = password_hash($password, PASSWORD_DEFAULT);
        $role  = 'customer';   // set by the server, never taken from the form
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
            if ($ex->getCode() === 1062) {   // duplicate email slipped through
                $errors[] = 'An account with this email already exists.';
            } else {
                error_log('Register failed: ' . $ex->getMessage());
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign up | Maintenance Hub</title>
</head>
<body>
    <h1>Create your account</h1>

    <?php foreach ($errors as $error): ?>
        <p style="color:red;"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" action="/register.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <p><label>Full name<br>
            <input type="text" name="full_name" value="<?= e($name) ?>" required></label></p>
        <p><label>Email<br>
            <input type="email" name="email" value="<?= e($email) ?>" required></label></p>
        <p><label>Phone (optional)<br>
            <input type="text" name="phone" value="<?= e($phone) ?>"></label></p>
        <p><label>Password<br>
            <input type="password" name="password" required></label></p>
        <p><label>Confirm password<br>
            <input type="password" name="confirm_password" required></label></p>

        <button type="submit">Sign up</button>
    </form>

    <p>Already have an account? <a href="/login.php">Log in</a></p>
</body>
</html>
