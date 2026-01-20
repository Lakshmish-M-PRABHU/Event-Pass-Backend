<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5500");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

session_start();
require "../config/events_db.php";

$studentId = $_SESSION['student_id'] ?? null;
if (!$studentId) {
    http_response_code(401);
    echo json_encode(["error" => "Student not logged in"]);
    exit;
}

$eventId = $_POST['event_id'] ?? null;
$notes   = $_POST['completion_notes'] ?? null;

if (!$eventId || !$notes) {
    http_response_code(400);
    echo json_encode(["error" => "Missing details"]);
    exit;
}

$stmt = $eventDB->prepare("
    SELECT attendance, status, date_to
    FROM events
    WHERE event_id = ? AND student_id = ?
");
$stmt->execute([$eventId, $studentId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$event ||
    (int)$event['attendance'] !== 1 ||
    $event['status'] !== 'approved' ||
    strtotime($event['date_to']) > strtotime(date('Y-m-d'))
) {
    http_response_code(403);
    echo json_encode([
        "error" => "Completion not allowed",
        "debug" => $event
    ]);
    exit;
}


// Update completion
$stmt = $eventDB->prepare("
    UPDATE events
    SET status = 'completed',
        completion_notes = ?
    WHERE event_id = ?
");
$stmt->execute([$notes, $eventId]);

// (Optional) notify TG + Coordinator here

echo json_encode([
    "success" => true,
    "message" => "Event marked as completed"
]);
