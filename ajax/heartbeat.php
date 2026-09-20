<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['authenticated' => false]);
    exit;
}

$_SESSION['last_activity'] = time();
echo json_encode(['authenticated' => true]);
