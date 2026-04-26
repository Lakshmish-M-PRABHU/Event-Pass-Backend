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
    $facultyDept = null;
    $facultyActivityType = null;

    if (in_array($roleUpper, ['HOD', 'COORDINATOR'], true)) {
        $facultyStmt = $collegeDB->prepare("SELECT department, activity_type FROM faculty WHERE faculty_code = ? LIMIT 1");
        $facultyStmt->execute([$facultyId]);
        $facultyInfo = $facultyStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $facultyDept = $facultyInfo['department'] ?? null;
        $facultyActivityType = $facultyInfo['activity_type'] ?? null;
    }

    $requestedStatus = strtolower(trim($_GET['status'] ?? 'all'));
    $allowedStatuses = ['approved', 'completed'];
    $statusList = in_array($requestedStatus, $allowedStatuses, true) ? [$requestedStatus] : $allowedStatuses;

    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');
    $category = trim($_GET['category'] ?? 'All');
    $department = trim($_GET['department'] ?? 'All');
    $semester = trim($_GET['semester'] ?? 'All');
    $student = trim($_GET['student'] ?? 'All');

    $statusPlaceholders = implode(',', array_fill(0, count($statusList), '?'));

    $query = "
        SELECT DISTINCT
            s.studid,
            s.usn AS student_usn,
            s.name AS student_name,
            s.department AS student_department,
            s.semester AS student_semester,
            p.event_id,
            p.tracking_id,
            p.activity_name,
            p.activity_type,
            p.date_from,
            p.date_to,
            p.status,
            p.current_stage,
            p.application_type,
            p.participation_role
        FROM (
            SELECT
                e.event_id,
                e.tracking_id,
                e.activity_name,
            e.activity_type,
            e.date_from,
            e.date_to,
            e.submission_date,
            e.status,
            COALESCE(e.approval_stage, 'TG') AS current_stage,
            e.application_type,
            e.studid,
                'leader' AS participation_role
            FROM events e
            WHERE UPPER(e.status) IN ($statusPlaceholders)

            UNION ALL

            SELECT
                e.event_id,
                e.tracking_id,
                e.activity_name,
                e.activity_type,
                e.date_from,
                e.date_to,
                e.submission_date,
                e.status,
                COALESCE(e.approval_stage, 'TG') AS current_stage,
                e.application_type,
                tm.studid,
                'team_member' AS participation_role
            FROM events e
            JOIN team_members tm ON tm.event_id = e.event_id AND tm.is_leader = 0
            WHERE UPPER(e.status) IN ($statusPlaceholders)
        ) p
        JOIN college_db.students s ON s.studid = p.studid
        WHERE 1 = 1
    ";

    $params = array_merge(array_map('strtoupper', $statusList), array_map('strtoupper', $statusList));

    if ($from !== '' || $to !== '') {
        $startDate = $from !== '' ? $from : $to;
        $endDate = $to !== '' ? $to : $from;
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $query .= " AND (
            DATE(p.date_from) BETWEEN ? AND ?
            OR DATE(p.date_to) BETWEEN ? AND ?
            OR (DATE(p.date_from) <= ? AND DATE(p.date_to) >= ?)
        ) ";
        $params[] = $startDate;
        $params[] = $endDate;
        $params[] = $startDate;
        $params[] = $endDate;
        $params[] = $startDate;
        $params[] = $endDate;
    }

    if ($category !== '' && $category !== 'All') {
        $query .= " AND p.activity_type = ? ";
        $params[] = $category;
    }

    if ($department !== '' && $department !== 'All') {
        $query .= " AND s.department = ? ";
        $params[] = $department;
    }

    if ($semester !== '' && $semester !== 'All') {
        $query .= " AND s.semester = ? ";
        $params[] = $semester;
    }

    if ($student !== '' && $student !== 'All') {
        $query .= " AND s.studid = ? ";
        $params[] = (int)$student;
    }

    if ($roleUpper === 'TG' && !empty($facultyId)) {
        $query .= "
            AND EXISTS (
                SELECT 1
                FROM college_db.student_tg_mapping stm
                WHERE stm.faculty_code = ?
                  AND (stm.active = 1 OR stm.active IS NULL)
                  AND stm.studid = s.studid
            )
        ";
        $params[] = $facultyId;
    } elseif ($roleUpper === 'COORDINATOR' && !empty($facultyActivityType)) {
        $query .= " AND UPPER(p.activity_type) = UPPER(?) ";
        $params[] = $facultyActivityType;
    } elseif ($roleUpper === 'HOD' && !empty($facultyDept)) {
        $query .= " AND UPPER(s.department) = UPPER(?) ";
        $params[] = $facultyDept;
    }

    $query .= " ORDER BY s.name ASC, p.date_from DESC, p.submission_date DESC";

    $stmt = $eventDB->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
