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

        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['name']    = $user['full_name'];
            $_SESSION['role']    = $user['role'];
            redirect('/dashboard.php');
        }
        $errors[] = 'Incorrect email or password.';
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/../app/includes/header.php';
?>

<div class="auth-wrap">
    <div class="card auth-card">
        <h1>Welcome back</h1>
        <p class="lead">Log in to manage your bookings.</p>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">Account created. You can log in now.</div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" action="/login.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Log in</button>
        </form>

        <p style="margin-top:18px;">New here? <a href="/register.php">Create an account</a></p>
    </div>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
