<?php
header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
// get_event_for_completion.php
header("Content-Type: application/json");
session_start();
require "../config/events_db.php";

$studentId = $_SESSION['studid'] ?? null;
if (!$studentId) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$eventId = $_GET['event_id'] ?? null;
if (!$eventId) {
    http_response_code(400);
    echo json_encode(["error" => "Event ID required"]);
    exit;
}

$stmt = $eventDB->prepare("
    SELECT event_id, activity_name, activity_type, date_from, date_to, status, attendance
    FROM events
    WHERE event_id = ? AND studid = ?
");
$stmt->execute([$eventId, $studentId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    echo json_encode(["error" => "Event not found"]);
    exit;
}

// IMPORTANT: only block access if attendance is explicitly 0
if ($event['attendance'] === '0') {
    http_response_code(403);
    echo json_encode(["error" => "Attendance rejected"]);
    exit;
}

// attendance === NULL → buttons will show in frontend
echo json_encode(["event" => $event]);
