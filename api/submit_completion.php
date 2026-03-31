<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://127.0.0.1:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
session_start();
require "../config/events_db.php";
require "../config/college_db.php";

header("Content-Type: application/json");

$studentId = $_SESSION['studid'] ?? null;
if (!$studentId) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$eventId     = $_POST['event_id'] ?? null;
$experience  = $_POST['experience'] ?? null;
$achievements= $_POST['achievements'] ?? null;
$position    = $_POST['position'] ?? null;
$rating      = $_POST['rating'] ?? null;

if (!$eventId || !$experience || !$rating) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

// Validate event
$stmt = $eventDB->prepare("
    SELECT status, application_type, activity_name, tracking_id
    FROM events 
    WHERE event_id = ? AND studid = ?
");
$stmt->execute([$eventId, $studentId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event || $event['status'] !== 'approved') {
    http_response_code(403);
    echo json_encode(["error" => "Invalid event state"]);
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
        echo json_encode(["error" => "Only team leader can submit completion"]);
        exit;
    }
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
    echo json_encode(["error" => "Invalid event state"]);
    exit;
}

$stmt = $eventDB->prepare("
    SELECT completion_id FROM event_completions WHERE event_id = ?
");
$stmt->execute([$eventId]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(["error" => "Completion already submitted"]);
    exit;
}

/* Save completion */
$stmt = $eventDB->prepare("
    INSERT INTO event_completions (event_id, experience, achievements, position, rating)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$eventId, $experience, $achievements, $position, $rating]);

/* Mark event completed */
$stmt = $eventDB->prepare("
    UPDATE events
    SET status = 'completed',
        completion_submitted_at = NOW()
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
function getTGFacultyCodes(PDO $collegeDB, array $studentIds) {
    if (empty($studentIds)) return [];
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $stmt = $collegeDB->prepare("
        SELECT DISTINCT faculty_code
        FROM student_tg_mapping
        WHERE studid IN ($placeholders)
        AND (active = 1 OR active IS NULL)
    ");
    $stmt->execute($studentIds);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function insertNotification(PDO $eventDB, $facultyCode, $eventId, $title, $message, $type) {
    if (!$facultyCode) return;
    $check = $eventDB->prepare("
        SELECT 1 FROM notifications
        WHERE faculty_code = ? AND event_id = ? AND type = ? AND title = ?
        LIMIT 1
    ");
    $check->execute([$facultyCode, $eventId, $type, $title]);
    if ($check->fetchColumn()) return;

    $stmt = $eventDB->prepare("
        INSERT INTO notifications (faculty_code, event_id, title, message, type)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$facultyCode, $eventId, $title, $message, $type]);
}

try {
    $studentIds = [$studentId];

    if (($event['application_type'] ?? '') === 'team') {
        $teamStmt = $eventDB->prepare("
            SELECT studid
            FROM team_members
            WHERE event_id = ?
        ");
        $teamStmt->execute([$eventId]);
        $studentIds = $teamStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $tgCodes = getTGFacultyCodes($collegeDB, $studentIds);
    $title = "Completion Submitted";
    $message = "Completion submitted for " .
        ($event['activity_name'] ?? 'the event') .
        ($event['tracking_id'] ? " (Event " . $event['tracking_id'] . ")" : "") .
        ". Please review the completion details.";

    foreach ($tgCodes as $tgCode) {
        insertNotification($eventDB, $tgCode, $eventId, $title, $message, "completion");
    }
} catch (Exception $e) {
    // Notifications are best-effort; do not block completion success.
}

echo json_encode(["success" => true]);
