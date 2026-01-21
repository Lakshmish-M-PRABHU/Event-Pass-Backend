<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5501");
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

$data = json_decode(file_get_contents("php://input"), true);
$eventId  = $data['event_id'] ?? null;
$attended = $data['attended'] ?? null;

if (!$eventId || !is_bool($attended)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

// Fetch event
$stmt = $eventDB->prepare("
    SELECT status, date_to 
    FROM events 
    WHERE event_id = ? AND student_id = ?
");
$stmt->execute([$eventId, $studentId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    echo json_encode(["error" => "Event not found"]);
    exit;
}

// Check approval + date
$today = date('Y-m-d');

if ($event['status'] !== 'approved' || $event['date_to'] > $today) {
    http_response_code(403);
    echo json_encode(["error" => "Attendance not allowed yet"]);
    exit;
}

/* ===============================
   STUDENT DID NOT ATTEND
================================ */
if ($attended === false) {

    $stmt = $eventDB->prepare("
        UPDATE events
        SET attendance = 'no',
            status = 'rejected'
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);

    // (Optional) notify TG here

    echo json_encode([
        "message" => "Marked as not attended. Event rejected."
    ]);
    exit;
}

/* ===============================
   STUDENT ATTENDED
================================ */
$stmt = $eventDB->prepare("
    UPDATE events
    SET attendance = 'yes'
    WHERE event_id = ?
");
$stmt->execute([$eventId]);

echo json_encode([
    "success" => true,
    "redirect" => "event_completion.html?event_id=$eventId"
]);
