<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

$facultyId = $_SESSION['faculty_code'] ?? null;
$role = $_SESSION['role'] ?? null;

if (!$facultyId || !$role) {
    http_response_code(401);
    echo json_encode(["error" => "Faculty not logged in"]);
    exit;
}

require "../../config/events_db.php";
require "../../config/college_db.php";

try {
    $roleUpper = strtoupper($role);
    
    $query = "
    SELECT DISTINCT
        e.event_id,
        e.activity_name,
        e.activity_type,
        e.date_from,
        e.date_to,
        e.uploaded_file,
        e.status,
        e.approval_stage AS current_stage,
        e.application_type,
        e.financial_amount,
        e.financial_purpose,
        s.name AS student_name,
        s.usn AS student_usn
    FROM events e
    JOIN college_db.students s ON s.studid = e.studid
    LEFT JOIN team_members tm ON e.event_id = tm.event_id
    WHERE e.status = 'pending'
    AND NOT EXISTS (
        -- Exclude events where attendance has been verified
        SELECT 1 FROM attendance a
        WHERE a.event_id = e.event_id
        AND a.final_status = 'verified'
    )
    ";

    $params = [];

    if ($roleUpper === 'TG') {
        $query .= " AND (e.studid IN (SELECT studid FROM college_db.student_tg_mapping WHERE faculty_code = ?) OR tm.studid IN (SELECT studid FROM college_db.student_tg_mapping WHERE faculty_code = ?)) AND UPPER(e.approval_stage) = ?";
        $params[] = $facultyId;
        $params[] = $facultyId;
        $params[] = $roleUpper;
    }
    elseif ($roleUpper === 'COORDINATOR') {
        $query .= " AND UPPER(e.activity_type) = (SELECT UPPER(activity_type) FROM college_db.faculty WHERE faculty_code = ?) AND UPPER(e.approval_stage) = ?";
        $params[] = $facultyId;
        $params[] = $roleUpper;
    } elseif (in_array($roleUpper, ['HOD', 'DEAN', 'PRINCIPAL'])) {
        $query .= " AND UPPER(e.approval_stage) = ?";
        $params[] = $roleUpper;
    }

    $query .= " ORDER BY e.submission_date DESC";

    $stmt = $eventDB->prepare($query);
    $stmt->execute($params);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as &$event) {
        $event['images'] = [$event['uploaded_file']] ?? [];
        
        if ($event['application_type'] === 'team') {
            if ($roleUpper === 'TG') {
                // Show only team members mapped to this TG
                $tmStmt = $eventDB->prepare("
                    SELECT tm.member_id, tm.usn, tm.name, tm.studid,
                           COALESCE(tma.status, 'pending') as approval_status
                    FROM team_members tm
                    JOIN college_db.student_tg_mapping stm ON tm.studid = stm.studid
                    LEFT JOIN team_member_approvals tma ON tma.member_id = tm.member_id 
                        AND tma.event_id = tm.event_id 
                        AND tma.role = ? 
                        AND tma.faculty_code = ?
                    WHERE tm.event_id = ? AND stm.faculty_code = ?
                    ORDER BY tm.is_leader DESC
                ");
                $tmStmt->execute([$roleUpper, $facultyId, $event['event_id'], $facultyId]);
            } else {
                // Show all team members for other roles
                $tmStmt = $eventDB->prepare("
                    SELECT tm.member_id, tm.usn, tm.name, tm.studid,
                           COALESCE(tma.status, 'pending') as approval_status
                    FROM team_members tm
                    LEFT JOIN team_member_approvals tma ON tma.member_id = tm.member_id 
                        AND tma.event_id = tm.event_id 
                        AND tma.role = ? 
                        AND tma.faculty_code = ?
                    WHERE tm.event_id = ? 
                    ORDER BY tm.is_leader DESC
                ");
                $tmStmt->execute([$roleUpper, $facultyId, $event['event_id']]);
            }
            $event['team_members'] = $tmStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // For individual events, check if this faculty has already approved
            $approvalStmt = $eventDB->prepare("
                SELECT status 
                FROM event_approvals 
                WHERE event_id = ? AND role = ? AND faculty_code = ?
            ");
            $approvalStmt->execute([$event['event_id'], $roleUpper, $facultyId]);
            $approval = $approvalStmt->fetch(PDO::FETCH_ASSOC);
            $event['approval_status'] = $approval ? $approval['status'] : 'pending';
        }
        
        if (in_array($roleUpper, ['TG', 'DEAN'])) {
            $event['financial_details'] = [
                'financial_amount' => $event['financial_amount'] ?? null,
                'financial_purpose' => $event['financial_purpose'] ?? null,
            ];
        }
        unset($event['uploaded_file']);
    }

    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
