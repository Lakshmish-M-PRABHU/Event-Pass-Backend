<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://127.0.0.1:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require "../config/events_db.php";
require "../config/college_db.php";

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
        SELECT event_id, application_type, status, activity_name, tracking_id
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
