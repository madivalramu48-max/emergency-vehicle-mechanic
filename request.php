<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new request
    $data = json_decode(file_get_contents('php://input'), true);

    $userId = intval($data['user_id'] ?? 0);
    $lat = floatval($data['lat'] ?? 0);
    $lon = floatval($data['lon'] ?? 0);
    $description = trim($data['description'] ?? '');
    $phone = trim($data['phone'] ?? '');

    if ($userId <= 0 || $lat == 0 || $lon == 0 || empty($description)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
        exit();
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO requests (user_id, lat, lon, description, phone, status, created_at) 
             VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([$userId, $lat, $lon, $description, $phone]);

        echo json_encode([
            'success' => true,
            'message' => 'Request created',
            'requestId' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create request', 'message' => $e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get requests
    $status = $_GET['status'] ?? 'all';
    $userId = intval($_GET['user_id'] ?? 0);

    try {
        if ($status === 'all') {
            $stmt = $pdo->prepare("SELECT * FROM requests ORDER BY created_at DESC");
            $stmt->execute();
        } elseif ($userId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM requests WHERE status = ? ORDER BY created_at DESC");
            $stmt->execute([$status]);
        }

        $requests = $stmt->fetchAll();
        echo json_encode(['success' => true, 'requests' => $requests]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch requests', 'message' => $e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update request status
    $data = json_decode(file_get_contents('php://input'), true);

    $requestId = intval($data['request_id'] ?? 0);
    $status = trim($data['status'] ?? '');
    $mechanicId = intval($data['mechanic_id'] ?? 0);

    if ($requestId <= 0 || empty($status)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE requests SET status = ?, assigned_mechanic_id = ? WHERE id = ?");
        $stmt->execute([$status, $mechanicId > 0 ? $mechanicId : null, $requestId]);

        echo json_encode(['success' => true, 'message' => 'Request updated']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update request', 'message' => $e->getMessage()]);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>