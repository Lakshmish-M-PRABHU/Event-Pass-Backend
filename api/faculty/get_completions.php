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
require_once "../../helpers/notification_service.php";

try {
    $roleUpper = strtoupper($role);
    $facultyDept = null;
    if (in_array($roleUpper, ['HOD', 'COORDINATOR'], true)) {
        $deptStmt = $collegeDB->prepare("SELECT department FROM faculty WHERE faculty_code = ? LIMIT 1");
        $deptStmt->execute([$facultyId]);
        $facultyDept = $deptStmt->fetchColumn() ?: null;
    }

    $query = "
        SELECT 
            ec.completion_id,
            ec.event_id,
            ec.experience,
            ec.achievements,
            ec.position,
            ec.rating,
            ec.submitted_at,
            ec.certificate_files,
            ec.photo_files,
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
    } elseif ($roleUpper === 'HOD') {
        $query .= "
            AND EXISTS (
                SELECT 1
                FROM team_members tm2
                JOIN college_db.students s2 ON s2.studid = tm2.studid
                JOIN college_db.faculty hf ON hf.faculty_code = ?
                WHERE tm2.event_id = e.event_id
                  AND UPPER(s2.department) = UPPER(hf.department)
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
        $completion['certificate_files'] = json_decode($completion['certificate_files'] ?? '[]', true) ?: [];
        $completion['photo_files'] = json_decode($completion['photo_files'] ?? '[]', true) ?: [];
        $completion['certificate_urls'] = array_map(
            static fn($file) => "http://localhost/Event-Pass-Backend/uploads/completions/event_" . $completion['event_id'] . "/certificates/" . $file,
            $completion['certificate_files']
        );
        $completion['photo_urls'] = array_map(
            static fn($file) => "http://localhost/Event-Pass-Backend/uploads/completions/event_" . $completion['event_id'] . "/photos/" . $file,
            $completion['photo_files']
        );

        if (($completion['application_type'] ?? '') === 'team') {
            $teamStmt = $eventDB->prepare("
                SELECT tm.member_id, tm.studid, tm.usn, tm.name, s2.department, tm.is_leader
                FROM team_members tm
                JOIN college_db.students s2 ON s2.studid = tm.studid
                JOIN college_db.faculty hf ON hf.faculty_code = ?
                WHERE tm.event_id = ?
                AND (
                    ? <> 'HOD'
                    OR UPPER(s2.department) = UPPER(hf.department)
                )
                ORDER BY tm.is_leader DESC, tm.member_id ASC
            ");
            $teamStmt->execute([$facultyId, $completion['event_id'], $roleUpper]);
            $teamMembers = $teamStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($roleUpper === 'HOD' && !empty($facultyDept)) {
                $teamMembers = array_values(array_filter($teamMembers, static function ($member) use ($facultyDept) {
                    $memberDept = strtoupper(trim((string)($member['department'] ?? '')));
                    return $memberDept !== '' && $memberDept === strtoupper(trim((string)$facultyDept));
                }));
            }
            $completion['team_members'] = $teamMembers;
        }
    }

    echo json_encode($completions);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
