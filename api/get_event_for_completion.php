<?php
header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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
    SELECT event_id, activity_name, activity_type, date_from, date_to, status, attendance, application_type
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

if (($event['application_type'] ?? '') === 'team') {
    $leaderStmt = $eventDB->prepare("
        SELECT 1
        FROM team_members
        WHERE event_id = ? AND studid = ? AND is_leader = 1
        LIMIT 1
    ");
    $leaderStmt->execute([$eventId, $studentId]);
    if (!$leaderStmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(["error" => "Only team leader can complete this event"]);
        exit;
    }
}

// Completion is allowed once event is approved and student has submitted "attended = yes".
if ($event['status'] !== 'approved') {
    http_response_code(403);
    echo json_encode(["error" => "Completion not allowed yet"]);
    exit;
}

$attendanceStmt = $eventDB->prepare("
    SELECT attended, final_status
    FROM attendance
    WHERE event_id = ? AND studid = ?
    ORDER BY attendance_id DESC
    LIMIT 1
");
$attendanceStmt->execute([$eventId, $studentId]);
$attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

$attendedValue = $attendance['attended'] ?? null;
$attendedYes = (
    $attendedValue === 1 ||
    $attendedValue === '1' ||
    $attendedValue === true ||
    (is_string($attendedValue) && strtolower($attendedValue) === 'yes')
);

if (!$attendance || !$attendedYes || (($attendance['final_status'] ?? '') === 'rejected')) {
    http_response_code(403);
    echo json_encode(["error" => "Completion not allowed yet"]);
    exit;
}

echo json_encode(["event" => $event]);
?>
