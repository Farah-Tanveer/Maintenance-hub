<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare(
            'SELECT id, full_name, password_hash, role, is_active
             FROM users WHERE email = ?'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // One vague message for every failure, so attackers cannot
        // tell which emails have accounts.
        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);   // new session ID after login
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['name']    = $user['full_name'];
            $_SESSION['role']    = $user['role'];
            redirect('/dashboard.php');
        }
        $errors[] = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in | Maintenance Hub</title>
</head>
<body>
    <h1>Log in</h1>

    <?php if (isset($_GET['registered'])): ?>
        <p style="color:green;">Account created. You can log in now.</p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p style="color:red;"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" action="/login.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <p><label>Email<br>
            <input type="email" name="email" value="<?= e($email) ?>" required></label></p>
        <p><label>Password<br>
            <input type="password" name="password" required></label></p>
        <button type="submit">Log in</button>
    </form>

    <p>New here? <a href="/register.php">Create an account</a></p>
</body>
</html>
