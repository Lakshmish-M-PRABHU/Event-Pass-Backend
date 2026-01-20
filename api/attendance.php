<?php
header("Access-Control-Allow-Origin: http://localhost:5500");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
require "../config/events_db.php";
require "../config/college_db.php";

$studentId = $_SESSION['student_id'] ?? null;
if (!$studentId) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$eventId  = $data['event_id'] ?? null;
$attended = $data['attended'] ?? null;

if (!$eventId || !isset($attended)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

// Convert boolean → 1 / 0
$attendanceValue = $attended ? 1 : 0;

/* 1️⃣ Update attendance */
$stmt = $eventDB->prepare("
    UPDATE events 
    SET attendance = ? 
    WHERE event_id = ? AND student_id = ?
");
$updated = $stmt->execute([$attendanceValue, $eventId, $studentId]);

if (!$updated) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update attendance"]);
    exit;
}

// Optional: Add notification to TG if attendance is marked (adjust as needed)
if ($attended) {
    $stmt = $collegeDB->prepare("SELECT faculty_id FROM student_tg_mapping WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $tg = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tg) {
        $stmt = $eventDB->prepare("INSERT INTO notifications (faculty_id, event_id, title, message, type) VALUES (?, ?, 'Attendance Marked', 'Student has marked attendance for Event ID $eventId.', 'attendance')");
        $stmt->execute([$tg['faculty_id'], $eventId]);
    }
}

/* 4️⃣ Final response */
echo json_encode([
    "success" => true,
    "message" => $attended
        ? "Attendance confirmed!"
        : "Attendance rejected. TG notified."
]);