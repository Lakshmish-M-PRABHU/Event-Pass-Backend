<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ================= CORS =================
$allowedOrigin = "http://localhost:5500";
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

// ================= FETCH NOTIFICATIONS =================
try {
    $stmt = $eventDB->prepare("
        SELECT notification_id, event_id, title, message, type, created_at
        FROM notifications
        WHERE faculty_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$facultyId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($notifications);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}