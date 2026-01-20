<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ================= CORS =================
$allowedOrigin = "http://localhost:5500";
header("Access-Control-Allow-Origin: $allowedOrigin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ================= SESSION =================
session_start();

$facultyId = $_SESSION['faculty_id'] ?? null;
$facultyRole = $_SESSION['role'] ?? null;

if (!$facultyId || !$facultyRole) {
    http_response_code(401);
    echo json_encode(["error" => "Faculty not logged in"]);
    exit;
}

// ================= DATABASE =================
require "../../config/events_db.php";   // $eventDB
require "../../config/college_db.php";  // $collegeDB

// ================= FETCH EVENTS =================
try {
    $role = strtoupper($facultyRole);

    if ($role === 'COORDINATOR') {

        // 🔹 Get coordinator domain from DB (NOT session)
        $stmt = $collegeDB->prepare("
            SELECT activity_type 
            FROM faculty 
            WHERE faculty_id = ?
        ");
        $stmt->execute([$facultyId]);
        $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$faculty || !$faculty['activity_type']) {
            echo json_encode([]);
            exit;
        }

        $domain = strtoupper($faculty['activity_type']);

        $stmt = $eventDB->prepare("
            SELECT 
                e.event_id,
                e.activity_name,
                e.activity_type,
                e.date_from,
                e.date_to,
                e.uploaded_file,
                e.status,
                e.approval_stage AS current_stage,
                s.name AS student_name,
                s.usn AS student_usn
            FROM events e
            JOIN college_db.students s ON s.student_id = e.student_id
            WHERE e.approval_stage = 'COORDINATOR'
              AND e.status = 'pending'
              AND UPPER(e.activity_type) = ?
            ORDER BY e.submission_date DESC
        ");

        $stmt->execute([$domain]);

    } else {

        // TG, HOD, DEAN, PRINCIPAL
        $stmt = $eventDB->prepare("
            SELECT 
                e.event_id,
                e.activity_name,
                e.activity_type,
                e.date_from,
                e.date_to,
                e.uploaded_file,
                e.status,
                e.approval_stage AS current_stage,
                s.name AS student_name,
                s.usn AS student_usn
            FROM events e
            JOIN college_db.students s ON s.student_id = e.student_id
            WHERE e.approval_stage = ?
              AND e.status = 'pending'
            ORDER BY e.submission_date DESC
        ");

        $stmt->execute([strtoupper($facultyRole)]);
    }

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}

