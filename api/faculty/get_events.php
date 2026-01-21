<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ================= CORS =================
$allowedOrigin = "http://localhost:5501";
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
$role = $_SESSION['role'] ?? null; // Fixed: Use 'role' consistently

if (!$facultyId || !$role) {
    http_response_code(401);
    echo json_encode(["error" => "Faculty not logged in"]);
    exit;
}

// ================= DATABASE =================
require "../../config/events_db.php";   // $eventDB
require "../../config/college_db.php";  // $collegeDB

// ================= FETCH EVENTS =================
try {
    $roleUpper = strtoupper($role);
    $query = "
        SELECT 
            e.event_id,
            e.activity_name,
            e.activity_type,
            e.date_from,
            e.date_to,
            e.uploaded_file,  -- Use for single file/image; add 'images' column if needed for multiple
            e.status,
            e.approval_stage AS current_stage,
            s.name AS student_name,
            s.usn AS student_usn
        FROM events e
        JOIN college_db.students s ON s.student_id = e.student_id
        WHERE e.status = 'pending'
    ";
    $params = [];

    if ($roleUpper === 'TG') {
        // TG sees events from their mapped students
        $query .= " AND e.student_id IN (SELECT student_id FROM college_db.student_tg_mapping WHERE faculty_id = ?)";
        $params[] = $facultyId;
    } elseif ($roleUpper === 'COORDINATOR') {
        // Coordinators see events from their domain
        $query .= " AND UPPER(e.activity_type) = (SELECT UPPER(activity_type) FROM college_db.faculty WHERE faculty_id = ?)";
        $params[] = $facultyId;
    } elseif (in_array($roleUpper, ['HOD', 'DEAN', 'PRINCIPAL'])) {
        // These see events at their approval stage
        $query .= " AND e.approval_stage = ?";
        $params[] = $roleUpper;
    }

    $query .= " ORDER BY e.submission_date DESC";

    $stmt = $eventDB->prepare($query);
    $stmt->execute($params);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conditionally add financial details and images for response
    foreach ($events as &$event) {
        // Use uploaded_file as images array (adjust if you add a separate images column)
        $event['images'] = [$event['uploaded_file']] ?? [];  // Single file as array; decode if JSON
        if (in_array($roleUpper, ['TG', 'DEAN'])) {
            // Use existing financial fields from table
            $event['financial_details'] = [
                'financial_amount' => $event['financial_amount'] ?? null,
                'financial_purpose' => $event['financial_purpose'] ?? null,
            ];
        }
        // Remove uploaded_file from output if not needed
        unset($event['uploaded_file']);
    }

    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}