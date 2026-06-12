<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id   = intval($data['id'] ?? 0);

if ($id) {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE logs SET alarm_triggered=1 WHERE id=? AND user_id=?");
    $stmt->execute([$id, currentUser()['id']]);
}

echo json_encode(['success' => true]);
