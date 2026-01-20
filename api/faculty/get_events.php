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
            e.uploaded_file,
            e.images,  -- Added: JSON array of image paths
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
        $query .= " AND e.student_id IN (SELECT student_id FROM student_tg_mapping WHERE faculty_id = ?)";
        $params[] = $facultyId;
    } elseif ($roleUpper === 'COORDINATOR') {
        // Coordinators see events from their domain
        $query .= " AND UPPER(e.activity_type) = (SELECT UPPER(activity_type) FROM faculty WHERE faculty_id = ?)";
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
        $event['images'] = json_decode($event['images'] ?? '[]', true); // Decode images array
        if (in_array($roleUpper, ['TG', 'DEAN'])) {
            // Fetch and add financial details (assuming fields exist in events table)
            $event['financial_details'] = [
                'budget' => $event['budget'] ?? null,
                'expenses' => $event['expenses'] ?? null,
            ];
        }
        // Remove sensitive fields for non-authorized roles if needed
    }

    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}