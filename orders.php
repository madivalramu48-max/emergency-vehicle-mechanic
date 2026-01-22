<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $userId = intval($_GET['user_id'] ?? 0);

    try {
        if ($action === 'get_users') {
            $stmt = $pdo->prepare(
                "SELECT u.*, COUNT(r.id) as total_orders FROM users u 
                 LEFT JOIN requests r ON u.id = r.user_id 
                 WHERE u.role = 'user' 
                 GROUP BY u.id ORDER BY u.created_at DESC"
            );
            $stmt->execute();
            $users = $stmt->fetchAll();
            echo json_encode(['success' => true, 'users' => $users]);

        } elseif ($action === 'get_mechanics') {
            $stmt = $pdo->prepare(
                "SELECT u.*, COUNT(r.id) as completed_jobs FROM users u 
                 LEFT JOIN requests r ON u.id = r.assigned_mechanic_id 
                 WHERE u.role = 'mechanic' 
                 GROUP BY u.id ORDER BY u.created_at DESC"
            );
            $stmt->execute();
            $mechanics = $stmt->fetchAll();
            echo json_encode(['success' => true, 'mechanics' => $mechanics]);

        } elseif ($action === 'get_analytics') {
            $stmt1 = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
            $stmt1->execute();
            $totalUsers = $stmt1->fetch()['total'];

            $stmt2 = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'mechanic'");
            $stmt2->execute();
            $totalMechanics = $stmt2->fetch()['total'];

            $stmt3 = $pdo->prepare("SELECT COUNT(*) as total FROM requests");
            $stmt3->execute();
            $totalOrders = $stmt3->fetch()['total'];

            $stmt4 = $pdo->prepare("SELECT COUNT(*) as total FROM requests WHERE status = 'completed' AND DATE(updated_at) = CURDATE()");
            $stmt4->execute();
            $completedToday = $stmt4->fetch()['total'];

            echo json_encode([
                'success' => true,
                'total_users' => $totalUsers,
                'total_mechanics' => $totalMechanics,
                'total_orders' => $totalOrders,
                'completed_today' => $completedToday
            ]);

        } elseif ($userId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            $orders = $stmt->fetchAll();
            echo json_encode(['success' => true, 'orders' => $orders]);

        } else {
            $stmt = $pdo->prepare(
                "SELECT r.*, u.name as user_name, u.phone FROM requests r 
                 JOIN users u ON r.user_id = u.id 
                 ORDER BY r.created_at DESC"
            );
            $stmt->execute();
            $orders = $stmt->fetchAll();
            echo json_encode(['success' => true, 'orders' => $orders]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch data', 'message' => $e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
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
        $stmt = $pdo->prepare("UPDATE requests SET status = ?, assigned_mechanic_id = ?, updated_at = NOW() WHERE id = ?");
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