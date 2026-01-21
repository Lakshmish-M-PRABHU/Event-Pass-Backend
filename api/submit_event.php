<?php
// ==============================
// CONFIG & ERRORS
// ==============================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5501"); // Your frontend origin
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// ==============================
// SESSION
// ==============================
session_start();
$studentId = $_SESSION['student_id'] ?? null;
if (!$studentId) {
    echo json_encode(["error"=>"Please login first"]);
    exit;
}

// ==============================
// DATABASE CONFIG
// ==============================
require "../config/college_db.php";  // defines $collegeDB (PDO)
require "../config/events_db.php";    // defines $eventDB (PDO)

// ==============================
// VALIDATE STUDENT
// ==============================
$stmt = $collegeDB->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo json_encode(["error"=>"Invalid student"]);
    exit;
}
$financialAssistance = $_POST['financial_assistance'] ?? 'no';
$financialPurpose = $_POST['financial_purpose'] ?? null;
$financialAmount = $_POST['financial_amount'] ?? null;

if ($financialAssistance === 'yes') {
    if (empty($financialPurpose) || empty($financialAmount)) {
        echo json_encode(["error" => "Financial details required"]);
        exit;
    }
}

// ==============================
// GET FORM DATA
// ==============================
$activity_type  = $_POST['activity_type'] ?? null;
$activity_name  = $_POST['activity_name'] ?? null;
$date_from      = $_POST['date_from'] ?? null;
$date_to        = $_POST['date_to'] ?? null;
$activity_level = $_POST['activity_level'] ?? null;
$residency      = $_POST['residency'] ?? null;
$event_url      = $_POST['event_url'] ?? null;

// Validate required fields
if (!$activity_type || !$activity_name || !$date_from || !$date_to || !$activity_level || !$residency) {
    echo json_encode(["error"=>"All required fields must be filled"]);
    exit;
}

// ==============================
// HANDLE FILE UPLOAD
// ==============================
$uploaded_file_name = null;
if (isset($_FILES['event_file']) && $_FILES['event_file']['error'] === 0) {
    $uploadsDir = "../uploads/";
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }

    $uploaded_file_name = time() . "_" . basename($_FILES['event_file']['name']);
    $targetPath = $uploadsDir . $uploaded_file_name;

    if (!move_uploaded_file($_FILES['event_file']['tmp_name'], $targetPath)) {
        echo json_encode(["error"=>"Failed to upload file"]);
        exit;
    }
}


// ==============================
// INSERT EVENT
// ==============================
$trackingId = "EV-" . rand(100,999);

$stmt = $eventDB->prepare("
    INSERT INTO events 
    (student_id, tracking_id, activity_type, activity_name, date_from, date_to,
     activity_level, residency, event_url, uploaded_file,
     financial_assistance, financial_purpose, financial_amount, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");

try {
    $stmt->execute([
    $studentId,
    $trackingId,
    $activity_type,
    $activity_name,
    $date_from,
    $date_to,
    $activity_level,
    $residency,
    $event_url,
    $uploaded_file_name,   
    $financialAssistance,
    $financialPurpose,
    $financialAmount
  ]);
  $eventId = $eventDB->lastInsertId();

  // ==============================
  // INSERT APPROVAL FLOW
  // ==============================
  $roles = ['TG', 'COORDINATOR', 'HOD', 'DEAN', 'PRINCIPAL'];

  $approvalStmt = $eventDB->prepare("
      INSERT INTO event_approvals (event_id, role, status)
      VALUES (?, ?, 'pending')
  ");

  foreach ($roles as $role) {
      $approvalStmt->execute([$eventId, $role]);
  }


    echo json_encode([
        "success"=>true,
        "tracking_id"=>$trackingId
    ]);

} catch (PDOException $e) {
    echo json_encode(["error"=>"Database error: ".$e->getMessage()]);
}
