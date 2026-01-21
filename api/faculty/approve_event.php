<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

session_start();

if (!isset($_SESSION['faculty_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(["error" => "Faculty not logged in"]);
    exit;
}

require "../../config/events_db.php";
require "../../config/college_db.php";

$facultyId   = $_SESSION['faculty_id'];
$facultyRole = $_SESSION['role'];

$data = json_decode(file_get_contents("php://input"), true);
$eventId = $data['event_id'] ?? null;
$action  = strtolower($data['action'] ?? '');

$action = strtolower($action);

if ($facultyRole === 'COORDINATOR') {

    // Get event activity type
    $stmt = $eventDB->prepare("
        SELECT activity_type FROM events WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode(["error" => "Event not found"]);
        exit;
    }

    // Get coordinator domain from faculty table
    $stmt = $collegeDB->prepare("
        SELECT activity_type FROM faculty WHERE faculty_id = ?
    ");
    $stmt->execute([$facultyId]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$faculty || !$faculty['activity_type']) {
        http_response_code(403);
        echo json_encode(["error" => "Coordinator domain not set"]);
        exit;
    }

    if (strtoupper($event['activity_type']) !== strtoupper($faculty['activity_type'])) {
        http_response_code(403);
        echo json_encode(["error" => "Unauthorized coordinator"]);
        exit;
    }
}



if ($action === 'accepted') $action = 'accept';
if ($action === 'rejected') $action = 'reject';

if (!$eventId || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit;
}


if (!$eventId || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$flow = ['TG','COORDINATOR','HOD','DEAN','PRINCIPAL'];
$currentIndex = array_search($facultyRole, $flow);

if ($currentIndex === false) {
    http_response_code(403);
    echo json_encode(["error" => "Invalid role"]);
    exit;
}

/* ==============================
   REJECT EVENT
================================ */
if ($action === 'reject') {

    // 1️⃣ Update event_approvals
    $stmt = $eventDB->prepare("
        UPDATE event_approvals
        SET status = 'rejected',
            action_date = NOW()
        WHERE event_id = ?
          AND role = ?
    ");
    $stmt->execute([$eventId, $facultyRole]);

    // 2️⃣ Update main events table
    $stmt = $eventDB->prepare("
        UPDATE events
        SET status = 'rejected',
            approval_stage = ?
        WHERE event_id = ?
    ");
    $stmt->execute([$facultyRole, $eventId]);

    echo json_encode(["message" => "Event rejected by $facultyRole"]);
    exit;
}

/* ==============================
   APPROVE EVENT
================================ */

// 1️⃣ Mark this role approved
$stmt = $eventDB->prepare("
    UPDATE event_approvals
    SET status = 'approved',
        action_date = NOW()
    WHERE event_id = ?
      AND role = ?
");
$stmt->execute([$eventId, $facultyRole]);

// 2️⃣ Move to next stage OR finalize
if ($currentIndex < count($flow) - 1) {

    $nextStage = $flow[$currentIndex + 1];

    $stmt = $eventDB->prepare("
        UPDATE events
        SET approval_stage = ?
        WHERE event_id = ?
    ");
    $stmt->execute([$nextStage, $eventId]);

    echo json_encode(["message" => "Moved to $nextStage"]);
} else {

    // FINAL APPROVAL
    $stmt = $eventDB->prepare("
        UPDATE events
        SET status = 'approved',
            approval_stage = 'PRINCIPAL'
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);

    $stmt = $eventDB->prepare("SELECT student_id FROM events WHERE event_id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($event) {
        $stmt = $collegeDB->prepare("SELECT faculty_id FROM student_tg_mapping WHERE student_id = ?");
        $stmt->execute([$event['student_id']]);
        $tg = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tg) {
            $stmt = $eventDB->prepare("INSERT INTO notifications (faculty_id, event_id, title, message, type) VALUES (?, ?, 'Event Approved', 'Attendance approved for Event ID $eventId.', 'approval')");
            $stmt->execute([$tg['faculty_id'], $eventId]);
        }
    }
    
    echo json_encode(["message" => "Event fully approved"]);
}
