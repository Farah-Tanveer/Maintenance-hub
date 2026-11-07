<?php
require __DIR__ . '/../app/includes/auth.php';
require __DIR__ . '/../app/includes/db.php';
require __DIR__ . '/../app/includes/slots.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in.']);
    exit;
}

$date = $_GET['date'] ?? '';
if (!valid_booking_date($date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please choose a valid date.']);
    exit;
}

echo json_encode(get_slots($conn, $date));
