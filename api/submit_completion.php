<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
session_start();
require "../config/events_db.php";
require "../config/college_db.php";
require_once "../helpers/notification_service.php";

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

app_ensure_completion_attachment_columns($eventDB);

$stmt = $eventDB->prepare("SELECT completion_id FROM event_completions WHERE event_id = ?");
$stmt->execute([$eventId]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(["error" => "Completion already submitted"]);
    exit;
}

// Store uploaded completion assets
$completionBaseDir = "../uploads/completions/event_" . $eventId . "/";
$certificateFiles = [];
$photoFiles = [];

if (isset($_FILES['certificates'])) {
    $certificateFiles = app_collect_file_uploads($_FILES['certificates'], $completionBaseDir . "certificates", ['pdf', 'jpg', 'jpeg', 'png'], 10);
}

if (isset($_FILES['photos'])) {
    $photoFiles = app_collect_file_uploads($_FILES['photos'], $completionBaseDir . "photos", ['jpg', 'jpeg', 'png'], 3);
}

if (empty($photoFiles)) {
    http_response_code(400);
    echo json_encode(["error" => "At least one event photo is required"]);
    exit;
}

/* Save completion */
$stmt = $eventDB->prepare("
    INSERT INTO event_completions (event_id, experience, achievements, position, rating, certificate_files, photo_files)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $eventId,
    $experience,
    $achievements,
    $position,
    $rating,
    json_encode($certificateFiles),
    json_encode($photoFiles)
]);

/* Mark event completed */
$stmt = $eventDB->prepare("
    UPDATE events
    SET status = 'completed',
        completion_submitted_at = NOW()
    WHERE event_id = ?
");
$stmt->execute([$eventId]);

function getFacultyCode(PDO $collegeDB, string $role, ?string $department = null, ?string $activityType = null): ?int {
    if ($role === 'COORDINATOR') {
        if ($department && $activityType) {
            $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? AND department = ? AND activity_type = ? LIMIT 1");
            $stmt->execute([$role, $department, $activityType]);
            $code = $stmt->fetchColumn();
            if ($code) return (int)$code;
        }
        if ($department) {
            $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? AND department = ? LIMIT 1");
            $stmt->execute([$role, $department]);
            $code = $stmt->fetchColumn();
            if ($code) return (int)$code;
        }
        $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? LIMIT 1");
        $stmt->execute([$role]);
        $code = $stmt->fetchColumn();
        return $code ? (int)$code : null;
    }

    if ($role === 'HOD') {
        if ($department) {
            $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? AND department = ? LIMIT 1");
            $stmt->execute([$role, $department]);
            $code = $stmt->fetchColumn();
            if ($code) return (int)$code;
        }
        $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? LIMIT 1");
        $stmt->execute([$role]);
        $code = $stmt->fetchColumn();
        return $code ? (int)$code : null;
    }

    $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? LIMIT 1");
    $stmt->execute([$role]);
    $code = $stmt->fetchColumn();
    return $code ? (int)$code : null;
}

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
    $teamMembers = [];
    $leaderInfo = null;
    $activityType = $event['activity_type'] ?? null;

    $studentStmt = $collegeDB->prepare("SELECT studid, name, usn, department FROM students WHERE studid = ? LIMIT 1");
    $studentStmt->execute([$studentId]);
    $leaderInfo = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (($event['application_type'] ?? '') === 'team') {
        $teamStmt = $eventDB->prepare("
            SELECT tm.studid, tm.usn, tm.name, tm.department, tm.is_leader
            FROM team_members
            WHERE event_id = ?
        ");
        $teamStmt->execute([$eventId]);
        $teamMembers = $teamStmt->fetchAll(PDO::FETCH_ASSOC);
        $studentIds = array_map(static fn($member) => (int)$member['studid'], $teamMembers);
        foreach ($teamMembers as $member) {
            if (!empty($member['is_leader'])) {
                $leaderInfo = $member;
                break;
            }
        }
    }

    $tgCodes = getTGFacultyCodes($collegeDB, $studentIds);
    $coordinatorCode = null;
    if ($leaderInfo) {
        $coordinatorCode = getFacultyCode($collegeDB, 'COORDINATOR', $leaderInfo['department'] ?? null, $activityType);
    }

    $title = "Completion Submitted";
    $message = "Completion submitted for " .
        ($event['activity_name'] ?? 'the event') .
        ($event['tracking_id'] ? " (Event " . $event['tracking_id'] . ")" : "") .
        ". Please review the completion details.";

    foreach ($tgCodes as $tgCode) {
        insertNotification($eventDB, $tgCode, $eventId, $title, $message, "completion");

        $facultyEmail = app_get_faculty_email($collegeDB, (int)$tgCode);
        if ($facultyEmail) {
            $facultyHtml = app_build_email_html(
                "Completion submitted",
                [
                    "A completion form has been submitted and is ready for review.",
                    "The submission includes the experience summary, achievements, and uploaded evidence."
                ],
                [
                    "Event Name" => $event['activity_name'] ?? '-',
                    "Tracking ID" => $event['tracking_id'] ?? '-',
                    "Event ID" => $eventId,
                    "Submitted By" => $leaderInfo['name'] ?? 'Team Leader',
                    "Photo Count" => count($photoFiles),
                    "Certificate Count" => count($certificateFiles)
                ],
                [
                    "text" => "Open Faculty Dashboard",
                    "url" => "http://localhost:5501/teach-dash/faculty-dashboard.html"
                ]
            );
            $facultyText = app_build_email_text(
                "Completion submitted",
                [
                    "A completion form has been submitted and is ready for review.",
                    "The submission includes the experience summary, achievements, and uploaded evidence."
                ],
                [
                    "Event Name" => $event['activity_name'] ?? '-',
                    "Tracking ID" => $event['tracking_id'] ?? '-',
                    "Event ID" => $eventId
                ]
            );
            app_send_email($facultyEmail, $title, $facultyHtml, $facultyText);
        }
    }

    if ($coordinatorCode) {
        app_insert_notification($eventDB, $coordinatorCode, $eventId, $title, $message, "completion");
        $coordinatorEmail = app_get_faculty_email($collegeDB, $coordinatorCode);
        if ($coordinatorEmail) {
            $coordinatorHtml = app_build_email_html(
                "Completion submitted",
                [
                    "A completion form has been submitted and is ready for your review."
                ],
                [
                    "Event Name" => $event['activity_name'] ?? '-',
                    "Tracking ID" => $event['tracking_id'] ?? '-',
                    "Event ID" => $eventId
                ],
                [
                    "text" => "Open Faculty Dashboard",
                    "url" => "http://localhost:5501/teach-dash/faculty-dashboard.html"
                ]
            );
            $coordinatorText = app_build_email_text(
                "Completion submitted",
                ["A completion form has been submitted and is ready for your review."],
                [
                    "Event Name" => $event['activity_name'] ?? '-',
                    "Tracking ID" => $event['tracking_id'] ?? '-',
                    "Event ID" => $eventId
                ]
            );
            app_send_email($coordinatorEmail, $title, $coordinatorHtml, $coordinatorText);
        }
    }

    foreach ($teamMembers as $member) {
        $memberEmail = app_get_student_email($collegeDB, (int)$member['studid']);
        if ($memberEmail) {
            $memberHtml = app_build_email_html(
                "Team completion submitted",
                [
                    "Your team leader has submitted the completion form for the event."
                ],
                [
                    "Event Name" => $event['activity_name'] ?? '-',
                    "Tracking ID" => $event['tracking_id'] ?? '-',
                    "Event ID" => $eventId,
                    "Leader" => $leaderInfo['name'] ?? '-'
                ],
                [
                    "text" => "View Dashboard",
                    "url" => "http://localhost:5501/dashboard.html"
                ]
            );
            $memberText = app_build_email_text(
                "Team completion submitted",
                ["Your team leader has submitted the completion form for the event."],
                [
                    "Event Name" => $event['activity_name'] ?? '-',
                    "Tracking ID" => $event['tracking_id'] ?? '-',
                    "Event ID" => $eventId
                ]
            );
            app_send_email($memberEmail, "Team completion submitted: " . ($event['activity_name'] ?? 'Event'), $memberHtml, $memberText);
        }
    }
} catch (Exception $e) {
    // Notifications are best-effort; do not block completion success.
}

echo json_encode(["success" => true]);
