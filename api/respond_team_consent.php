<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require "../config/events_db.php";
require "../config/college_db.php";
require_once "../helpers/notification_service.php";

function ensureTeamConsentTable(PDO $eventDB) {
    $eventDB->exec("
        CREATE TABLE IF NOT EXISTS team_member_consents (
            consent_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            member_id INT NOT NULL,
            studid INT NOT NULL,
            consent_status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
            responded_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_event_member (event_id, member_id),
            INDEX idx_event_studid (event_id, studid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
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

$studentId = $_SESSION['studid'] ?? null;
if (!$studentId) {
    http_response_code(401);
    echo json_encode(["error" => "Student not logged in"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$eventId = $input['event_id'] ?? null;
$action = strtolower(trim($input['action'] ?? ''));

if (!$eventId || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

try {
    ensureTeamConsentTable($eventDB);
    $eventDB->beginTransaction();

    $eventStmt = $eventDB->prepare("
        SELECT event_id, application_type, status, activity_name, tracking_id, studid
        FROM events
        WHERE event_id = ?
        LIMIT 1
    ");
    $eventStmt->execute([$eventId]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event || ($event['application_type'] ?? '') !== 'team') {
        if ($eventDB->inTransaction()) {
            $eventDB->rollBack();
        }
        http_response_code(404);
        echo json_encode(["error" => "Team event not found"]);
        exit;
    }

    if (in_array($event['status'], ['rejected', 'approved', 'completed'])) {
        if ($eventDB->inTransaction()) {
            $eventDB->rollBack();
        }
        http_response_code(409);
        echo json_encode(["error" => "Consent cannot be updated for this event status"]);
        exit;
    }

    $memberStmt = $eventDB->prepare("
        SELECT member_id, studid, is_leader
        FROM team_members
        WHERE event_id = ? AND studid = ?
        LIMIT 1
    ");
    $memberStmt->execute([$eventId, $studentId]);
    $member = $memberStmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        if ($eventDB->inTransaction()) {
            $eventDB->rollBack();
        }
        http_response_code(403);
        echo json_encode(["error" => "You are not part of this team"]);
        exit;
    }

    if ((int)$member['is_leader'] === 1) {
        if ($eventDB->inTransaction()) {
            $eventDB->rollBack();
        }
        http_response_code(400);
        echo json_encode(["error" => "Team leader is auto-confirmed"]);
        exit;
    }

    $status = ($action === 'accept') ? 'accepted' : 'rejected';

    $upsertStmt = $eventDB->prepare("
        INSERT INTO team_member_consents (event_id, member_id, studid, consent_status, responded_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            consent_status = VALUES(consent_status),
            responded_at = VALUES(responded_at)
    ");
    $upsertStmt->execute([$eventId, $member['member_id'], $studentId, $status]);

    $teamStmt = $eventDB->prepare("
        SELECT tm.member_id, tm.studid, tm.usn, tm.name, tm.is_leader
        FROM team_members tm
        WHERE tm.event_id = ?
        ORDER BY tm.is_leader DESC, tm.member_id ASC
    ");
    $teamStmt->execute([$eventId]);
    $teamMembers = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

    $studentLookup = $collegeDB->prepare("SELECT name, usn FROM students WHERE studid = ? LIMIT 1");
    $respondedBy = [];
    $studentLookup->execute([$studentId]);
    $respondedBy = $studentLookup->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Unknown', 'usn' => '-'];

    $teamSubject = "Team consent update: " . ($event['activity_name'] ?? 'Event') . " (" . ($event['tracking_id'] ?? '-') . ")";
    $teamDetails = [
        "Event Name" => $event['activity_name'] ?? '-',
        "Tracking ID" => $event['tracking_id'] ?? '-',
        "Latest Response" => ucfirst($status) . " by " . ($respondedBy['name'] ?? 'Unknown') . " (" . ($respondedBy['usn'] ?? '-') . ")",
        "Your Status" => ucfirst($status)
    ];
    $teamHtml = app_build_email_html(
        "Team consent updated",
        [
            "One team member has responded to the consent request.",
            "Please review the latest team consent status in the dashboard."
        ],
        $teamDetails,
        [
            "text" => "View Dashboard",
            "url" => "http://localhost:5501/dashboard.html"
        ]
    );
    $teamText = app_build_email_text(
        "Team consent updated",
        [
            "One team member has responded to the consent request.",
            "Please review the latest team consent status in the dashboard."
        ],
        $teamDetails
    );

    foreach ($teamMembers as $teamMember) {
        $recipientEmail = app_get_student_email($collegeDB, (int)$teamMember['studid']);
        if ($recipientEmail) {
            app_send_email($recipientEmail, $teamSubject, $teamHtml, $teamText);
        }
    }

    if ($status === 'rejected') {
        $rejectEventStmt = $eventDB->prepare("UPDATE events SET status = 'rejected' WHERE event_id = ?");
        $rejectEventStmt->execute([$eventId]);
    }

    $summaryStmt = $eventDB->prepare("
        SELECT
            SUM(CASE WHEN COALESCE(tc.consent_status, CASE WHEN tm.is_leader = 1 THEN 'accepted' ELSE 'pending' END) = 'accepted' THEN 1 ELSE 0 END) AS accepted_count,
            SUM(CASE WHEN COALESCE(tc.consent_status, CASE WHEN tm.is_leader = 1 THEN 'accepted' ELSE 'pending' END) = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN COALESCE(tc.consent_status, CASE WHEN tm.is_leader = 1 THEN 'accepted' ELSE 'pending' END) = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
        FROM team_members tm
        LEFT JOIN team_member_consents tc
            ON tc.event_id = tm.event_id AND tc.member_id = tm.member_id
        WHERE tm.event_id = ?
    ");
    $summaryStmt->execute([$eventId]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    $allMembersConsented = ((int)($summary['pending_count'] ?? 0) === 0 && (int)($summary['rejected_count'] ?? 0) === 0);

    if ($allMembersConsented) {
        $teamStmt = $eventDB->prepare("
            SELECT studid
            FROM team_members
            WHERE event_id = ?
        ");
        $teamStmt->execute([$eventId]);
        $studentIds = $teamStmt->fetchAll(PDO::FETCH_COLUMN);
        $tgCodes = getTGFacultyCodes($collegeDB, $studentIds);

        $title = "Team Consent Completed";
        $message = "All team members confirmed participation for " .
            ($event['activity_name'] ?? 'the event') .
            ($event['tracking_id'] ? " (Event " . $event['tracking_id'] . ")" : "") .
            ". Approval can proceed.";
        foreach ($tgCodes as $tgCode) {
            insertNotification($eventDB, $tgCode, $eventId, $title, $message, "approval");

            $facultyEmail = app_get_faculty_email($collegeDB, (int)$tgCode);
            if ($facultyEmail) {
                $facultyHtml = app_build_email_html(
                    "Team consent completed",
                    [
                        "All team members have accepted participation.",
                        "Please review and confirm the event approval flow from your dashboard."
                    ],
                    [
                        "Event Name" => $event['activity_name'] ?? '-',
                        "Tracking ID" => $event['tracking_id'] ?? '-',
                        "Event ID" => $eventId,
                        "Next Action" => "Confirm the event from the faculty dashboard"
                    ],
                    [
                        "text" => "Open Faculty Dashboard",
                        "url" => "http://localhost:5501/teach-dash/faculty-dashboard.html"
                    ]
                );
                $facultyText = app_build_email_text(
                    "Team consent completed",
                    [
                        "All team members have accepted participation.",
                        "Please review and confirm the event approval flow from your dashboard."
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
    }

    if ($eventDB->inTransaction()) {
        $eventDB->commit();
    }

    echo json_encode([
        "success" => true,
        "consent_status" => $status,
        "all_members_consented" => $allMembersConsented,
        "summary" => [
            "accepted" => (int)($summary['accepted_count'] ?? 0),
            "pending" => (int)($summary['pending_count'] ?? 0),
            "rejected" => (int)($summary['rejected_count'] ?? 0)
        ]
    ]);
} catch (Exception $e) {
    if ($eventDB->inTransaction()) {
        $eventDB->rollBack();
    }
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
