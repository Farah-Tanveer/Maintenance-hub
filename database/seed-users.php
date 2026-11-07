<?php
// Creates demo accounts for local testing and lab demos.
// Run from the project root: php database/seed-users.php
// Safe to run again: it resets these accounts to the values below.

require __DIR__ . '/../app/includes/db.php';

$accounts = [
    ['Demo Admin',    'admin@maintenancehub.local',    'Admin@12345',    'admin'],
    ['Demo Worker',   'worker@maintenancehub.local',   'Worker@12345',   'worker'],
    ['Demo Customer', 'customer@maintenancehub.local', 'Customer@12345', 'customer'],
];

$stmt = $conn->prepare(
    'INSERT INTO users (full_name, email, password_hash, role)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        password_hash = VALUES(password_hash),
        role = VALUES(role),
        is_active = 1'
);

foreach ($accounts as [$name, $email, $password, $role]) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt->bind_param('ssss', $name, $email, $hash, $role);
    $stmt->execute();
    echo str_pad($role, 9) . $email . '  /  ' . $password . PHP_EOL;
}
$stmt->close();
