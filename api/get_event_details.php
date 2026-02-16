<?php
header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

session_start();
require "../config/events_db.php";
require "../config/college_db.php";

$input = json_decode(file_get_contents('php://input'), true);
$eventId = $input['event_id'] ?? null;
$studentId = $_SESSION['studid'] ?? null;

if (!$studentId) {
    echo json_encode(["error" => "Student not logged in"]);
    exit;
}

// Get event - check if student is leader OR team member
if ($eventId) {
    $stmt = $eventDB->prepare("
        SELECT e.* FROM events e
        LEFT JOIN team_members tm ON e.event_id = tm.event_id
        WHERE e.event_id = ? AND (e.studid = ? OR tm.studid = ?)
    ");
    $stmt->execute([$eventId, $studentId, $studentId]);
} else {
    $stmt = $eventDB->prepare("
        SELECT DISTINCT e.* FROM events e
        LEFT JOIN team_members tm ON e.event_id = tm.event_id
        WHERE e.studid = ? OR tm.studid = ?
        ORDER BY e.submission_date DESC LIMIT 1
    ");
    $stmt->execute([$studentId, $studentId]);
}

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo json_encode(["error" => "Event not found"]);
    exit;
}

// For team events, get the logged-in student's team member info
$currentStudentMemberInfo = null;
$currentStudentApprovals = [];
if ($event['application_type'] === 'team') {
    $memberStmt = $eventDB->prepare("
        SELECT member_id, studid, usn, name, department, is_leader
        FROM team_members
        WHERE event_id = ? AND studid = ?
    ");
    $memberStmt->execute([$event['event_id'], $studentId]);
    $currentStudentMemberInfo = $memberStmt->fetch(PDO::FETCH_ASSOC);
    
    // If member not found, check if student is the leader
    if (!$currentStudentMemberInfo) {
        if ($event['studid'] == $studentId) {
            // Student is the leader, get their info from team_members as leader
            $leaderStmt = $eventDB->prepare("
                SELECT member_id, studid, usn, name, department, is_leader
                FROM team_members
                WHERE event_id = ? AND is_leader = 1
            ");
            $leaderStmt->execute([$event['event_id']]);
            $currentStudentMemberInfo = $leaderStmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    // Get current student's individual approvals if member info exists
    if ($currentStudentMemberInfo) {
        $flow = ['TG', 'COORDINATOR', 'HOD', 'DEAN', 'PRINCIPAL'];
        foreach ($flow as $role) {
            $memberApprovalStmt = $eventDB->prepare("
                SELECT status, action_date
                FROM team_member_approvals
                WHERE event_id = ? AND member_id = ? AND role = ?
                ORDER BY action_date DESC
                LIMIT 1
            ");
            $memberApprovalStmt->execute([$event['event_id'], $currentStudentMemberInfo['member_id'], $role]);
            $memberApproval = $memberApprovalStmt->fetch(PDO::FETCH_ASSOC);
            
            $currentStudentApprovals[] = [
                "role" => $role,
                "status" => $memberApproval ? strtolower($memberApproval['status']) : 'pending',
                "date" => $memberApproval['action_date'] ?? null
            ];
        }
    }
}

// Get existing approvals based on application type
$flow = ['TG', 'COORDINATOR', 'HOD', 'DEAN', 'PRINCIPAL'];
$fullApprovals = [];

if ($event['application_type'] === 'team') {
    // For team events, we need to check ALL team members' approvals
    // Get all team members first
    $teamStmt = $eventDB->prepare("SELECT member_id FROM team_members WHERE event_id = ?");
    $teamStmt->execute([$event['event_id']]);
    $teamMembers = $teamStmt->fetchAll(PDO::FETCH_COLUMN);
    $totalMembers = count($teamMembers);
    
    if ($totalMembers > 0) {
        // For each role, check the status across all team members
        foreach ($flow as $role) {
            $roleApproved = 0;
            $roleRejected = 0;
            $latestDate = null;
            
            // Check each team member's approval status for this role
            foreach ($teamMembers as $memberId) {
                $approvalStmt = $eventDB->prepare("
                    SELECT status, action_date 
                    FROM team_member_approvals 
                    WHERE event_id = ? AND member_id = ? AND role = ?
                    ORDER BY action_date DESC
                    LIMIT 1
                ");
                $approvalStmt->execute([$event['event_id'], $memberId, $role]);
                $approval = $approvalStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($approval) {
                    if ($approval['status'] === 'approved') {
                        $roleApproved++;
                    } elseif ($approval['status'] === 'rejected') {
                        $roleRejected++;
                    }
                    
                    // Track latest action date
                    if ($approval['action_date'] && (!$latestDate || $approval['action_date'] > $latestDate)) {
                        $latestDate = $approval['action_date'];
                    }
                }
            }
            
            // Determine overall status for this role
            if ($roleRejected > 0) {
                $status = 'rejected';
            } elseif ($roleApproved === $totalMembers) {
                $status = 'approved';
            } else {
                $status = 'pending';
            }
            
            $fullApprovals[] = [
                "role" => $role,
                "status" => $status,
                "date" => $latestDate
            ];
        }
    } else {
        // No team members found, all pending
        foreach ($flow as $role) {
            $fullApprovals[] = [
                "role" => $role,
                "status" => "pending",
                "date" => null
            ];
        }
    }
} else {
    // For individual events, get approvals from event_approvals
    $stmt = $eventDB->prepare("
        SELECT role, status, action_date 
        FROM event_approvals 
        WHERE event_id = ?
        ORDER BY 
            CASE role
                WHEN 'TG' THEN 1
                WHEN 'COORDINATOR' THEN 2
                WHEN 'HOD' THEN 3
                WHEN 'DEAN' THEN 4
                WHEN 'PRINCIPAL' THEN 5
            END
    ");
    $stmt->execute([$event['event_id']]);
    $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map approvals to full flow
    foreach ($flow as $role) {
        $found = null;
        foreach ($approvals as $a) {
            if (strtoupper($a['role']) === $role) {
                $found = $a;
                break;
            }
        }
        
        if ($found) {
            $fullApprovals[] = [
                "role" => $role,
                "status" => strtolower($found['status']),
                "date" => $found['action_date']
            ];
        } else {
            $fullApprovals[] = [
                "role" => $role,
                "status" => "pending",
                "date" => null
            ];
        }
    }
}

// Check if attendance can be shown
$currentDate = date('Y-m-d');
$statusCheck = $event['status'] === 'approved';
$dateCheck = $event['date_to'] <= $currentDate;
$showAttendance = $statusCheck && $dateCheck ? true : false;

// Get attendance status for the LOGGED-IN STUDENT (not the leader)
$attendanceStmt = $eventDB->prepare("
    SELECT 
        a.attendance_id,
        a.attended,
        a.proof_file,
        a.current_stage,
        a.final_status,
        a.created_at
    FROM attendance a
    WHERE a.event_id = ? AND a.studid = ?
    ORDER BY a.created_at DESC
    LIMIT 1
");
$attendanceStmt->execute([$event['event_id'], $studentId]);
$attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

// Get attendance approvals if attendance exists
$attendanceApprovals = [];
if ($attendance) {
    // First, try to get from attendance_approvals (correct table)
    $approvalStmt = $eventDB->prepare("
        SELECT 
            aa.role,
            LOWER(aa.status) as status,
            aa.action_date,
            f.name AS faculty_name
        FROM attendance_approvals aa
        LEFT JOIN college_db.faculty f ON f.faculty_code = aa.faculty_code
        WHERE aa.attendance_id = ?
        ORDER BY 
            CASE aa.role
                WHEN 'TG' THEN 1
                WHEN 'COORDINATOR' THEN 2
                WHEN 'HOD' THEN 3
                WHEN 'DEAN' THEN 4
                WHEN 'PRINCIPAL' THEN 5
            END
    ");
    $approvalStmt->execute([$attendance['attendance_id']]);
    $attendanceApprovals = $approvalStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no attendance approvals found in attendance_approvals table,
    // check team_member_approvals as fallback (for team events only)
    if (empty($attendanceApprovals) && $event['application_type'] === 'team' && $currentStudentMemberInfo) {
        $fallbackStmt = $eventDB->prepare("
            SELECT 
                tma.role,
                LOWER(tma.status) as status,
                tma.action_date,
                f.name AS faculty_name
            FROM team_member_approvals tma
            LEFT JOIN college_db.faculty f ON f.faculty_code = tma.faculty_code
            WHERE tma.event_id = ? AND tma.member_id = ?
            ORDER BY 
                CASE tma.role
                    WHEN 'TG' THEN 1
                    WHEN 'COORDINATOR' THEN 2
                    WHEN 'HOD' THEN 3
                    WHEN 'DEAN' THEN 4
                    WHEN 'PRINCIPAL' THEN 5
                END
        ");
        $fallbackStmt->execute([$event['event_id'], $currentStudentMemberInfo['member_id']]);
        $attendanceApprovals = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Ensure all status values are lowercase for consistency
    foreach ($attendanceApprovals as &$aa) {
        $aa['status'] = strtolower($aa['status'] ?? 'pending');
    }
    unset($aa);
}

// Build response
$response = [
    "event" => $event,
    "approvals" => $fullApprovals,
    "showAttendance" => $showAttendance,
    "attendance" => $attendance ?: false,
    "attendance_approvals" => $attendanceApprovals
];

// Add current student's info for team events - ALWAYS include even if member not found
if ($event['application_type'] === 'team') {
    // Always include the logged-in student's ID
    $response['current_student_id'] = $studentId;
    $response['event_leader_studid'] = $event['studid']; // Leader's ID for reference
    
    if ($currentStudentMemberInfo) {
        // Ensure studid is included
        $response['current_student_member'] = [
            'member_id' => $currentStudentMemberInfo['member_id'],
            'studid' => $currentStudentMemberInfo['studid'], // This is the logged-in student's ID
            'usn' => $currentStudentMemberInfo['usn'],
            'name' => $currentStudentMemberInfo['name'],
            'department' => $currentStudentMemberInfo['department'],
            'is_leader' => $currentStudentMemberInfo['is_leader']
        ];
        $response['current_student_approvals'] = $currentStudentApprovals;
        $response['is_team_leader'] = ($currentStudentMemberInfo['is_leader'] == 1);
    } else {
        // Member info not found - this shouldn't happen, but include what we can
        $response['current_student_member'] = null;
        $response['current_student_approvals'] = [];
        $response['is_team_leader'] = ($event['studid'] == $studentId);
    }
}

echo json_encode($response);
?>