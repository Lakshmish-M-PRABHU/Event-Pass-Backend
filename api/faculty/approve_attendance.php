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

$facultyId = $_SESSION['faculty_code'];
$facultyRole = strtoupper($_SESSION['role']);

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
$attendanceId = $data['attendance_id'] ?? null;
$action = strtolower($data['action'] ?? ''); // 'approve' or 'reject'
$remarks = $data['remarks'] ?? null;

if (!$attendanceId || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

try {
    $eventDB->beginTransaction();

    // Verify attendance exists and is pending for this role
    $stmt = $eventDB->prepare("
        SELECT 
            a.attendance_id,
            a.event_id,
            a.studid,
            a.current_stage,
            a.final_status,
            e.application_type,
            e.activity_type
        FROM attendance a
        JOIN events e ON e.event_id = a.event_id
        JOIN attendance_approvals aa ON aa.attendance_id = a.attendance_id
        WHERE a.attendance_id = ?
        AND aa.role = ?
        AND aa.status = 'pending'
        AND a.current_stage = ?
    ");
    $stmt->execute([$attendanceId, $facultyRole, $facultyRole]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attendance) {
        $eventDB->rollBack();
        http_response_code(404);
        echo json_encode(["error" => "Attendance approval not found or already processed"]);
        exit;
    }

    // Role-specific authorization checks
    if ($facultyRole === 'TG') {
        $stmt = $collegeDB->prepare("
            SELECT COUNT(*) as count 
            FROM student_tg_mapping 
            WHERE faculty_code = ? AND studid = ?
        ");
        $stmt->execute([$facultyId, $attendance['studid']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            $eventDB->rollBack();
            http_response_code(403);
            echo json_encode(["error" => "You are not the TG for this student"]);
            exit;
        }
    } elseif ($facultyRole === 'COORDINATOR') {
        $stmt = $eventDB->prepare("
            SELECT e.activity_type 
            FROM events e 
            WHERE e.event_id = ?
        ");
        $stmt->execute([$attendance['event_id']]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $collegeDB->prepare("
            SELECT activity_type 
            FROM faculty 
            WHERE faculty_code = ?
        ");
        $stmt->execute([$facultyId]);
        $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$faculty || strtoupper($event['activity_type']) !== strtoupper($faculty['activity_type'])) {
            $eventDB->rollBack();
            http_response_code(403);
            echo json_encode(["error" => "Unauthorized coordinator"]);
            exit;
        }
    }

    $approvalFlow = ['TG', 'COORDINATOR', 'HOD', 'DEAN', 'PRINCIPAL'];
    $currentIndex = array_search($facultyRole, $approvalFlow);

    if ($action === 'reject') {
        // Reject attendance
        $stmt = $eventDB->prepare("
            UPDATE attendance_approvals 
            SET status = 'rejected', 
                faculty_code = ?, 
                remarks = ?,
                action_date = NOW()
            WHERE attendance_id = ? AND role = ?
        ");
        $stmt->execute([$facultyId, $remarks, $attendanceId, $facultyRole]);

        // Update attendance final status
        $stmt = $eventDB->prepare("
            UPDATE attendance 
            SET final_status = 'rejected',
                current_stage = ?
            WHERE attendance_id = ?
        ");
        $stmt->execute([$facultyRole, $attendanceId]);

        $eventDB->commit();

        $studentStmt = $collegeDB->prepare("SELECT name, usn, department FROM students WHERE studid = ? LIMIT 1");
        $studentStmt->execute([$attendance['studid']]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Student', 'usn' => '-', 'department' => null];

        $subject = "Attendance rejected: " . ($event['activity_name'] ?? 'Event');
        $details = [
            "Event ID" => $attendance['event_id'],
            "Student" => ($student['name'] ?? 'Student') . " (" . ($student['usn'] ?? '-') . ")",
            "Approved By" => $facultyRole,
            "Status" => "Rejected"
        ];
        $studentHtml = app_build_email_html(
            "Attendance rejected",
            [
                "Your attendance was rejected by " . $facultyRole . ".",
                "Please review the dashboard for the latest status."
            ],
            $details,
            [
                "text" => "Open Dashboard",
                "url" => "http://127.0.0.1:5501/dashboard.html"
            ]
        );
        $studentText = app_build_email_text(
            "Attendance rejected",
            [
                "Your attendance was rejected by " . $facultyRole . ".",
                "Please review the dashboard for the latest status."
            ],
            $details
        );

        $studentEmail = app_get_student_email($collegeDB, (int)$attendance['studid']);
        if ($studentEmail) {
            app_send_email($studentEmail, $subject, $studentHtml, $studentText);
        }

        if ($attendance['application_type'] === 'team') {
            $teamStmt = $eventDB->prepare("SELECT studid FROM team_members WHERE event_id = ?");
            $teamStmt->execute([$attendance['event_id']]);
            foreach ($teamStmt->fetchAll(PDO::FETCH_COLUMN) as $teamStudid) {
                $teamEmail = app_get_student_email($collegeDB, (int)$teamStudid);
                if ($teamEmail) {
                    app_send_email($teamEmail, $subject, $studentHtml, $studentText);
                }
            }
        }

        echo json_encode([
            "success" => true,
            "message" => "Attendance rejected"
        ]);
        exit;
    }

    // Approve attendance
    $stmt = $eventDB->prepare("
        UPDATE attendance_approvals 
        SET status = 'approved', 
            faculty_code = ?, 
            remarks = ?,
            action_date = NOW()
        WHERE attendance_id = ? AND role = ?
    ");
    $stmt->execute([$facultyId, $remarks, $attendanceId, $facultyRole]);

    // Check if there's a next stage
    if ($currentIndex < count($approvalFlow) - 1) {
        // Move to next stage
        $nextRole = $approvalFlow[$currentIndex + 1];
        $stmt = $eventDB->prepare("
            UPDATE attendance 
            SET current_stage = ?
            WHERE attendance_id = ?
        ");
        $stmt->execute([$nextRole, $attendanceId]);
        
        $message = "Attendance approved and forwarded to " . $nextRole;

        $studentStmt = $collegeDB->prepare("SELECT name, usn, department FROM students WHERE studid = ? LIMIT 1");
        $studentStmt->execute([$attendance['studid']]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Student', 'usn' => '-', 'department' => null];
        $studentEmail = app_get_student_email($collegeDB, (int)$attendance['studid']);

        $studentDetails = [
            "Event ID" => $attendance['event_id'],
            "Student" => ($student['name'] ?? 'Student') . " (" . ($student['usn'] ?? '-') . ")",
            "Approved By" => $facultyRole,
            "Next Step" => $nextRole,
            "Status" => "Approved"
        ];
        $studentHtml = app_build_email_html(
            "Attendance approved",
            [
                "Your attendance was approved by " . $facultyRole . ".",
                "It has now been forwarded to " . $nextRole . "."
            ],
            $studentDetails,
            [
                "text" => "Open Dashboard",
                "url" => "http://127.0.0.1:5501/dashboard.html"
            ]
        );
        $studentText = app_build_email_text(
            "Attendance approved",
            [
                "Your attendance was approved by " . $facultyRole . ".",
                "It has now been forwarded to " . $nextRole . "."
            ],
            $studentDetails
        );

        if ($studentEmail) {
            app_send_email($studentEmail, "Attendance approved: " . ($attendance['event_id'] ?? 'Event'), $studentHtml, $studentText);
        }

        $nextFacultyCode = getFacultyCode($collegeDB, $nextRole, $student['department'] ?? null, $attendance['activity_type'] ?? null);
        if ($nextFacultyCode) {
            app_insert_notification($eventDB, $nextFacultyCode, (int)$attendance['event_id'], "Attendance ready for review", "Attendance approved by {$facultyRole}. Please confirm the next stage.", "attendance");
            $nextFacultyEmail = app_get_faculty_email($collegeDB, $nextFacultyCode);
            if ($nextFacultyEmail) {
                $nextFacultyHtml = app_build_email_html(
                    "Attendance ready for review",
                    [
                        "Attendance has been approved by " . $facultyRole . " and is ready for your review."
                    ],
                    [
                        "Event ID" => $attendance['event_id'],
                        "Student" => ($student['name'] ?? 'Student') . " (" . ($student['usn'] ?? '-') . ")",
                        "Next Stage" => $nextRole
                    ],
                    [
                        "text" => "Open Faculty Dashboard",
                        "url" => "http://127.0.0.1:5501/teach-dash/faculty-dashboard.html"
                    ]
                );
                $nextFacultyText = app_build_email_text(
                    "Attendance ready for review",
                    [
                        "Attendance has been approved by " . $facultyRole . " and is ready for your review."
                    ],
                    [
                        "Event ID" => $attendance['event_id'],
                        "Student" => ($student['name'] ?? 'Student') . " (" . ($student['usn'] ?? '-') . ")"
                    ]
                );
                app_send_email($nextFacultyEmail, "Attendance ready for review", $nextFacultyHtml, $nextFacultyText);
            }
        }
    } else {
        // Last stage - mark as verified
        $stmt = $eventDB->prepare("
            UPDATE attendance 
            SET final_status = 'verified',
                current_stage = 'COMPLETED'
            WHERE attendance_id = ?
        ");
        $stmt->execute([$attendanceId]);
        
        // Update event completion status
        $stmt = $eventDB->prepare("
            UPDATE events 
            SET attendance = 1 
            WHERE event_id = ?
        ");
        $stmt->execute([$attendance['event_id']]);
        
        $message = "Attendance verified and completed";

        $studentStmt = $collegeDB->prepare("SELECT name, usn FROM students WHERE studid = ? LIMIT 1");
        $studentStmt->execute([$attendance['studid']]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Student', 'usn' => '-'];
        $studentEmail = app_get_student_email($collegeDB, (int)$attendance['studid']);
        $finalHtml = app_build_email_html(
            "Attendance confirmed",
            [
                "Your attendance has been fully verified and accepted."
            ],
            [
                "Event ID" => $attendance['event_id'],
                "Student" => ($student['name'] ?? 'Student') . " (" . ($student['usn'] ?? '-') . ")",
                "Final Status" => "Verified"
            ],
            [
                "text" => "Open Dashboard",
                "url" => "http://127.0.0.1:5501/dashboard.html"
            ]
        );
        $finalText = app_build_email_text(
            "Attendance confirmed",
            ["Your attendance has been fully verified and accepted."],
            [
                "Event ID" => $attendance['event_id'],
                "Student" => ($student['name'] ?? 'Student') . " (" . ($student['usn'] ?? '-') . ")",
                "Final Status" => "Verified"
            ]
        );
        if ($studentEmail) {
            app_send_email($studentEmail, "Attendance confirmed", $finalHtml, $finalText);
        }

        if ($attendance['application_type'] === 'team') {
            $teamStmt = $eventDB->prepare("SELECT studid FROM team_members WHERE event_id = ?");
            $teamStmt->execute([$attendance['event_id']]);
            foreach ($teamStmt->fetchAll(PDO::FETCH_COLUMN) as $teamStudid) {
                $teamEmail = app_get_student_email($collegeDB, (int)$teamStudid);
                if ($teamEmail) {
                    app_send_email($teamEmail, "Attendance confirmed", $finalHtml, $finalText);
                }
            }
        }
    }

    $eventDB->commit();
    echo json_encode([
        "success" => true,
        "message" => $message
    ]);

} catch (PDOException $e) {
    $eventDB->rollBack();
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
