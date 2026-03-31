<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://127.0.0.1:5501");
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

    $query = "
        SELECT 
            ec.completion_id,
            ec.event_id,
            ec.experience,
            ec.achievements,
            ec.position,
            ec.rating,
            ec.submitted_at,
            e.tracking_id,
            e.activity_name,
            e.activity_type,
            e.date_from,
            e.date_to,
            e.application_type,
            e.studid AS leader_id,
            s.name AS student_name,
            s.usn AS student_usn
        FROM event_completions ec
        JOIN events e ON e.event_id = ec.event_id
        JOIN college_db.students s ON s.studid = e.studid
        WHERE e.status = 'completed'
    ";

    $params = [];

    if ($roleUpper === 'TG') {
        $query .= "
            AND EXISTS (
                SELECT 1
                FROM college_db.student_tg_mapping stm
                WHERE stm.faculty_code = ?
                AND (stm.active = 1 OR stm.active IS NULL)
                AND stm.studid IN (
                    SELECT studid FROM team_members WHERE event_id = e.event_id
                    UNION SELECT e.studid
                )
            )
        ";
        $params[] = $facultyId;
    } elseif ($roleUpper === 'COORDINATOR') {
        $query .= "
            AND UPPER(e.activity_type) = (
                SELECT UPPER(activity_type)
                FROM college_db.faculty
                WHERE faculty_code = ?
            )
        ";
        $params[] = $facultyId;
    } else {
        // HOD, DEAN, PRINCIPAL see all completions
    }

    $query .= " ORDER BY ec.submitted_at DESC";

    $stmt = $eventDB->prepare($query);
    $stmt->execute($params);
    $completions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($completions as &$completion) {
        if (($completion['application_type'] ?? '') === 'team') {
            $teamStmt = $eventDB->prepare("
                SELECT tm.member_id, tm.studid, tm.usn, tm.name, tm.department, tm.is_leader
                FROM team_members tm
                WHERE tm.event_id = ?
                ORDER BY tm.is_leader DESC, tm.member_id ASC
            ");
            $teamStmt->execute([$completion['event_id']]);
            $completion['team_members'] = $teamStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode($completions);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
