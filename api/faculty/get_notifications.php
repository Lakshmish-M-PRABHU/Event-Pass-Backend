<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ================= CORS =================
$allowedOrigin = "http://localhost:5501";
header("Access-Control-Allow-Origin: $allowedOrigin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ================= SESSION =================
session_start();

$facultyId = $_SESSION['faculty_id'] ?? null;
if (!$facultyId) {
    http_response_code(401);
    echo json_encode(["error" => "Faculty not logged in"]);
    exit;
}

// ================= DATABASE =================
require "../../config/events_db.php";   // $eventDB

// ================= FETCH NOTIFICATIONS (ONLY FOR COMPLETED EVENTS) =================
try {
    $stmt = $eventDB->prepare("
        SELECT n.notification_id, n.event_id, n.title, n.message, n.type, n.created_at
        FROM notifications n
        INNER JOIN events e ON n.event_id = e.event_id
        WHERE n.faculty_id = ? AND e.status = 'completed'
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$facultyId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($notifications);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>