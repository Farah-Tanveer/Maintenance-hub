<?php
// Opens one database connection that the rest of the app reuses.

$config = require __DIR__ . '/../config/database.php';

// Make mysqli throw real errors instead of failing silently.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $config['host'],
        $config['user'],
        $config['password'],
        $config['name']
    );
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Log the real reason privately, show visitors a safe message.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Something went wrong. Please try again later.');
}
