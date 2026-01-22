<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'emergency_mechanic');
define('DB_USER', 'root');
define('DB_PASS', '');

// Server configuration
define('SOCKET_SERVER', 'http://localhost:3000');

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>