<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://127.0.0.1:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

session_start();

if (!isset($_SESSION['faculty_code'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(["error" => "Faculty not logged in"]);
    exit;
}

require "../../config/events_db.php";
require "../../config/college_db.php";
require_once "../../helpers/notification_service.php";

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

$facultyId = $_SESSION['faculty_code'];
$facultyRole = $_SESSION['role'];

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

$data = json_decode(file_get_contents("php://input"), true);
$eventId = $data['event_id'] ?? null;
$usn = $data['usn'] ?? null;
$action = strtolower($data['action'] ?? '');

if ($action === 'approve') $action = 'accept';
if ($action === 'approved') $action = 'accept';

if (!$eventId || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$stmt = $eventDB->prepare("SELECT activity_type, activity_name, tracking_id, application_type, studid FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

$studentDept = null;
if ($event) {
    $deptStmt = $collegeDB->prepare("SELECT department FROM students WHERE studid = ? LIMIT 1");
    $deptStmt->execute([$event['studid']]);
    $studentDept = $deptStmt->fetchColumn() ?: null;
}

if (!$event) {
    http_response_code(404);
    echo json_encode(["error" => "Event not found"]);
    exit;
}

// Get member_id from USN for team events
$memberId = null;
if ($event['application_type'] === 'team' && $usn) {
    $stmt = $eventDB->prepare("SELECT member_id, studid FROM team_members WHERE event_id = ? AND usn = ?");
    $stmt->execute([$eventId, $usn]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        http_response_code(404);
        echo json_encode(["error" => "Team member not found"]);
        exit;
    }
    $memberId = $member['member_id'];
    $studentId = $member['studid'];
} elseif ($event['application_type'] === 'individual') {
    $studentId = $event['studid'];
}

// Validate TG authorization
if ($facultyRole === 'TG') {
    $stmt = $collegeDB->prepare("SELECT COUNT(*) as count FROM student_tg_mapping WHERE faculty_code = ? AND studid = ?");
    $stmt->execute([$facultyId, $studentId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        http_response_code(403);
        echo json_encode(["error" => "You are not the TG for this student"]);
        exit;
    }
}

// Coordinator validation
if ($facultyRole === 'COORDINATOR') {
    $stmt = $collegeDB->prepare("SELECT activity_type FROM faculty WHERE faculty_code = ?");
    $stmt->execute([$facultyId]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$faculty || strtoupper($event['activity_type']) !== strtoupper($faculty['activity_type'])) {
        http_response_code(403);
        echo json_encode(["error" => "Unauthorized coordinator"]);
        exit;
    }
}

$flow = ['TG','COORDINATOR','HOD','DEAN','PRINCIPAL'];
$currentIndex = array_search($facultyRole, $flow);

if ($currentIndex === false) {
    http_response_code(403);
    echo json_encode(["error" => "Invalid role"]);
    exit;
}

try {
if ($event['application_type'] === 'team') {
        ensureTeamConsentTable($eventDB);
    }
    $eventDB->beginTransaction();

    if ($event['application_type'] === 'team') {

        $consentCheckStmt = $eventDB->prepare("
            SELECT
                SUM(CASE WHEN COALESCE(tc.consent_status, CASE WHEN tm.is_leader = 1 THEN 'accepted' ELSE 'pending' END) = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN COALESCE(tc.consent_status, CASE WHEN tm.is_leader = 1 THEN 'accepted' ELSE 'pending' END) = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
            FROM team_members tm
            LEFT JOIN team_member_consents tc
                ON tc.event_id = tm.event_id AND tc.member_id = tm.member_id
            WHERE tm.event_id = ?
        ");
        $consentCheckStmt->execute([$eventId]);
        $consentState = $consentCheckStmt->fetch(PDO::FETCH_ASSOC);

        if ((int)($consentState['rejected_count'] ?? 0) > 0) {
            $stmt = $eventDB->prepare("UPDATE events SET status = 'rejected' WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $eventDB->commit();
            http_response_code(409);
            echo json_encode(["error" => "A team member declined this event invitation."]);
            exit;
        }

        if ((int)($consentState['pending_count'] ?? 0) > 0) {
            if ($eventDB->inTransaction()) {
                $eventDB->rollBack();
            }
            http_response_code(409);
            echo json_encode(["error" => "Waiting for all team members to confirm participation."]);
            exit;
        }
    }

    if ($event['application_type'] === 'team') {
    if ($action === 'reject') {
            $stmt = $eventDB->prepare("UPDATE team_member_approvals SET status = 'rejected', action_date = NOW(), faculty_code = ? WHERE event_id = ? AND member_id = ? AND role = ?");
            $stmt->execute([$facultyId, $eventId, $memberId, $facultyRole]);

            // Any rejection in team flow rejects the event at current role.
            $stmt = $eventDB->prepare("UPDATE events SET status = 'rejected', approval_stage = ? WHERE event_id = ?");
            $stmt->execute([$facultyRole, $eventId]);

            $teamStmt = $eventDB->prepare("SELECT studid FROM team_members WHERE event_id = ?");
            $teamStmt->execute([$eventId]);
            $teamStudentIds = $teamStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($teamStudentIds as $studid) {
                $email = app_get_student_email($collegeDB, (int)$studid);
                if ($email) {
                    $html = app_build_email_html(
                        "Event rejected",
                        [
                            "Your team event was rejected by " . $facultyRole . "."
                        ],
                        [
                            "Event ID" => $eventId,
                            "Event Name" => $event['activity_name'] ?? '-',
                            "Approved By" => $facultyRole,
                            "Status" => "Rejected"
                        ],
                        [
                            "text" => "Open Dashboard",
                            "url" => "http://127.0.0.1:5501/dashboard.html"
                        ]
                    );
                    $text = app_build_email_text(
                        "Event rejected",
                        ["Your team event was rejected by " . $facultyRole . "."],
                        [
                            "Event ID" => $eventId,
                            "Event Name" => $event['activity_name'] ?? '-',
                            "Approved By" => $facultyRole
                        ]
                    );
                    app_send_email($email, "Event rejected: " . ($event['activity_name'] ?? 'Event'), $html, $text);
                }
            }
        } else {
            $stmt = $eventDB->prepare("UPDATE team_member_approvals SET status = 'approved', action_date = NOW(), faculty_code = ? WHERE event_id = ? AND member_id = ? AND role = ?");
            $stmt->execute([$facultyId, $eventId, $memberId, $facultyRole]);

            // Move stage only when this role has finished all team members.
            $stmt = $eventDB->prepare("
                SELECT COUNT(*) as pending_count
                FROM team_member_approvals
                WHERE event_id = ? AND role = ? AND status = 'pending'
            ");
            $stmt->execute([$eventId, $facultyRole]);
            $pendingForRole = $stmt->fetch(PDO::FETCH_ASSOC);

            if ((int)$pendingForRole['pending_count'] === 0) {
                if ($currentIndex < count($flow) - 1) {
                    $nextStage = $flow[$currentIndex + 1];
                    $stmt = $eventDB->prepare("UPDATE events SET approval_stage = ? WHERE event_id = ?");
                    $stmt->execute([$nextStage, $eventId]);

                    $nextFacultyCode = getFacultyCode($collegeDB, $nextStage, $studentDept, $event['activity_type'] ?? null);
                    if ($nextFacultyCode) {
                        app_insert_notification($eventDB, $nextFacultyCode, $eventId, "Event ready for review", "Previous stage approved. Please continue the approval flow.", "approval");
                        $nextFacultyEmail = app_get_faculty_email($collegeDB, $nextFacultyCode);
                        if ($nextFacultyEmail) {
                            $nextHtml = app_build_email_html(
                                "Event ready for review",
                                [
                                    "The previous approval stage has been completed.",
                                    "Please review and confirm the next stage."
                                ],
                                [
                                    "Event ID" => $eventId,
                                    "Event Name" => $event['activity_name'] ?? '-',
                                    "Current Stage" => $nextStage
                                ],
                                [
                                    "text" => "Open Faculty Dashboard",
                                    "url" => "http://127.0.0.1:5501/teach-dash/faculty-dashboard.html"
                                ]
                            );
                            $nextText = app_build_email_text(
                                "Event ready for review",
                                ["The previous approval stage has been completed. Please review and confirm the next stage."],
                                [
                                    "Event ID" => $eventId,
                                    "Event Name" => $event['activity_name'] ?? '-',
                                    "Current Stage" => $nextStage
                                ]
                            );
                            app_send_email($nextFacultyEmail, "Event ready for review", $nextHtml, $nextText);
                        }
                    }
                } else {
                    $stmt = $eventDB->prepare("UPDATE events SET status = 'approved' WHERE event_id = ?");
                    $stmt->execute([$eventId]);

                    $teamStmt = $eventDB->prepare("SELECT studid FROM team_members WHERE event_id = ?");
                    $teamStmt->execute([$eventId]);
                    $teamStudentIds = $teamStmt->fetchAll(PDO::FETCH_COLUMN);
                    $studentEmail = null;
                    if (!empty($teamStudentIds)) {
                        foreach ($teamStudentIds as $studid) {
                            $studentEmail = app_get_student_email($collegeDB, (int)$studid);
                            if ($studentEmail) {
                                $html = app_build_email_html(
                                    "Event approved",
                                    [
                                        "Your team event has been fully approved."
                                    ],
                                    [
                                        "Event ID" => $eventId,
                                        "Event Name" => $event['activity_name'] ?? '-',
                                        "Final Status" => "Approved"
                                    ],
                                    [
                                        "text" => "Open Dashboard",
                                        "url" => "http://127.0.0.1:5501/dashboard.html"
                                    ]
                                );
                                $text = app_build_email_text(
                                    "Event approved",
                                    ["Your team event has been fully approved."],
                                    [
                                        "Event ID" => $eventId,
                                        "Event Name" => $event['activity_name'] ?? '-'
                                    ]
                                );
                                app_send_email($studentEmail, "Event approved: " . ($event['activity_name'] ?? 'Event'), $html, $text);
                            }
                        }
                    }
                }
            }
        }
    } else {
        if ($action === 'reject') {
            $stmt = $eventDB->prepare("UPDATE event_approvals SET status = 'rejected', action_date = NOW(), faculty_code = ? WHERE event_id = ? AND role = ?");
            $stmt->execute([$facultyId, $eventId, $facultyRole]);
    
            $stmt = $eventDB->prepare("UPDATE events SET status = 'rejected', approval_stage = ? WHERE event_id = ?");
            $stmt->execute([$facultyRole, $eventId]);

            $studentEmail = app_get_student_email($collegeDB, (int)$event['studid']);
            if ($studentEmail) {
                $html = app_build_email_html(
                    "Event rejected",
                    [
                        "Your event was rejected by " . $facultyRole . "."
                    ],
                    [
                        "Event ID" => $eventId,
                        "Event Name" => $event['activity_name'] ?? '-',
                        "Tracking ID" => $event['tracking_id'] ?? '-',
                        "Approved By" => $facultyRole
                    ],
                    [
                        "text" => "Open Dashboard",
                        "url" => "http://127.0.0.1:5501/dashboard.html"
                    ]
                );
                $text = app_build_email_text(
                    "Event rejected",
                    ["Your event was rejected by " . $facultyRole . "."],
                    [
                        "Event ID" => $eventId,
                        "Event Name" => $event['activity_name'] ?? '-',
                        "Tracking ID" => $event['tracking_id'] ?? '-',
                        "Approved By" => $facultyRole
                    ]
                );
                app_send_email($studentEmail, "Event rejected: " . ($event['activity_name'] ?? 'Event'), $html, $text);
            }
        } else {
            $stmt = $eventDB->prepare("UPDATE event_approvals SET status = 'approved', action_date = NOW(), faculty_code = ? WHERE event_id = ? AND role = ?");
            $stmt->execute([$facultyId, $eventId, $facultyRole]);

            if ($currentIndex < count($flow) - 1) {
                $nextStage = $flow[$currentIndex + 1];
                $stmt = $eventDB->prepare("UPDATE events SET approval_stage = ? WHERE event_id = ?");
                $stmt->execute([$nextStage, $eventId]);

                $nextFacultyCode = getFacultyCode($collegeDB, $nextStage, $studentDept, $event['activity_type'] ?? null);
                if ($nextFacultyCode) {
                    app_insert_notification($eventDB, $nextFacultyCode, $eventId, "Event ready for review", "Previous stage approved. Please continue the approval flow.", "approval");
                    $nextFacultyEmail = app_get_faculty_email($collegeDB, $nextFacultyCode);
                    if ($nextFacultyEmail) {
                        $nextHtml = app_build_email_html(
                            "Event ready for review",
                            ["The previous approval stage has been completed."],
                            [
                                "Event ID" => $eventId,
                                "Event Name" => $event['activity_name'] ?? '-',
                                "Current Stage" => $nextStage
                            ],
                            [
                                "text" => "Open Faculty Dashboard",
                                "url" => "http://127.0.0.1:5501/teach-dash/faculty-dashboard.html"
                            ]
                        );
                        $nextText = app_build_email_text(
                            "Event ready for review",
                            ["The previous approval stage has been completed."],
                            [
                                "Event ID" => $eventId,
                                "Event Name" => $event['activity_name'] ?? '-',
                                "Current Stage" => $nextStage
                            ]
                        );
                        app_send_email($nextFacultyEmail, "Event ready for review", $nextHtml, $nextText);
                    }
                }
            } else {
                $stmt = $eventDB->prepare("UPDATE events SET status = 'approved' WHERE event_id = ?");
                $stmt->execute([$eventId]);

                $studentEmail = app_get_student_email($collegeDB, (int)$event['studid']);
                if ($studentEmail) {
                    $html = app_build_email_html(
                        "Event approved",
                        [
                            "Your event has been fully approved."
                        ],
                        [
                            "Event ID" => $eventId,
                            "Event Name" => $event['activity_name'] ?? '-',
                            "Final Status" => "Approved"
                        ],
                        [
                            "text" => "Open Dashboard",
                            "url" => "http://127.0.0.1:5501/dashboard.html"
                        ]
                    );
                    $text = app_build_email_text(
                        "Event approved",
                        ["Your event has been fully approved."],
                        [
                            "Event ID" => $eventId,
                            "Event Name" => $event['activity_name'] ?? '-'
                        ]
                    );
                    app_send_email($studentEmail, "Event approved: " . ($event['activity_name'] ?? 'Event'), $html, $text);
                }
            }
        }
    }

    if ($eventDB->inTransaction()) {
        $eventDB->commit();
    }
    echo json_encode(["message" => "Action completed successfully"]);

} catch (Exception $e) {
    if ($eventDB->inTransaction()) {
        $eventDB->rollBack();
    }
    echo json_encode(["error" => $e->getMessage()]);
}
?>
