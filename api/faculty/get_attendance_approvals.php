<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
    
    // Get pending attendance approvals for this faculty role
    $query = "
        SELECT 
            a.attendance_id,
            a.event_id,
            a.studid,
            a.attended,
            a.proof_file,
            a.remarks,
            a.current_stage,
            a.final_status,
            a.created_at,
            e.activity_name,
            e.activity_type,
            e.date_from,
            e.date_to,
            e.tracking_id,
            e.application_type,
            s.name AS student_name,
            s.usn AS student_usn,
            aa.status AS approval_status,
            aa.remarks AS approval_remarks
        FROM attendance a
        JOIN events e ON e.event_id = a.event_id
        JOIN college_db.students s ON s.studid = a.studid
        JOIN attendance_approvals aa ON aa.attendance_id = a.attendance_id
        WHERE aa.role = ?
        AND aa.status = 'pending'
        AND a.current_stage = ?
        AND a.final_status = 'pending'
    ";

    $params = [$roleUpper, $roleUpper];

    // Role-specific filtering
    if ($roleUpper === 'TG') {
        // TG can only see their own students
        $query .= " AND EXISTS (
            SELECT 1 FROM college_db.student_tg_mapping stg 
            WHERE stg.studid = a.studid 
            AND stg.faculty_code = ?
        )";
        $params[] = $facultyId;
    } elseif ($roleUpper === 'COORDINATOR') {
        // Coordinator can only see events of their activity type
        $query .= " AND EXISTS (
            SELECT 1 FROM college_db.faculty f 
            WHERE f.faculty_code = ? 
            AND UPPER(f.activity_type) = UPPER(e.activity_type)
        )";
        $params[] = $facultyId;
    } else {
        // HOD, DEAN, PRINCIPAL can see all
        // No additional filter needed
    }

    $query .= " ORDER BY a.created_at DESC";

    $stmt = $eventDB->prepare($query);
    $stmt->execute($params);
    $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For team events, get team members info
    foreach ($approvals as &$approval) {
        if ($approval['application_type'] === 'team') {
            $teamStmt = $eventDB->prepare("
                SELECT tm.member_id, tm.studid, tm.usn, tm.name, tm.department, tm.is_leader
                FROM team_members tm
                WHERE tm.event_id = ?
            ");
            $teamStmt->execute([$approval['event_id']]);
            $approval['team_members'] = $teamStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode($approvals);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>