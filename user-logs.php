<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $userId = intval($data['user_id'] ?? 0);
    $action = trim($data['action'] ?? '');
    $details = trim($data['details'] ?? '');
    $ipAddress = trim($data['ip_address'] ?? $_SERVER['REMOTE_ADDR']);

    if ($userId <= 0 || empty($action)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        exit();
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO user_logs (user_id, action, details, ip_address, created_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $action, $details, $ipAddress]);

        echo json_encode(['success' => true, 'message' => 'Log recorded']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record log', 'message' => $e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare(
            "SELECT l.*, u.name as user_name FROM user_logs l 
             JOIN users u ON l.user_id = u.id 
             ORDER BY l.created_at DESC LIMIT 1000"
        );
        $stmt->execute();
        $logs = $stmt->fetchAll();

        echo json_encode(['success' => true, 'logs' => $logs]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch logs', 'message' => $e->getMessage()]);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>