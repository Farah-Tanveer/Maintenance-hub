<?php
require __DIR__ . '/../app/includes/auth.php';

http_response_code(404);
$pageTitle = 'Page not found';
require __DIR__ . '/../app/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="card empty-state">
            <div class="icon">🔍</div>
            <h2>Page not found</h2>
            <p>The page you are looking for does not exist or has moved.</p>
            <p style="margin-top:16px;">
                <a href="/" class="btn btn-primary">Back to home</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
