<?php
// ==============================
// CONFIG & ERRORS
// ==============================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5501");
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
    echo json_encode(["error" => "Please login first"]);
    exit;
}

// ==============================
// DATABASE
// ==============================
require "../config/college_db.php";  // students DB
require "../config/events_db.php";   // events + attendance DB

// ==============================
// INPUT DATA
// ==============================
$event_id = $_POST['event_id'] ?? null;
$attended = $_POST['attended'] ?? null;
$remarks  = $_POST['remarks'] ?? null;

if (!$event_id || !$attended) {
    echo json_encode(["error" => "Event ID and attendance status required"]);
    exit;
}

// ==============================
// VALIDATE EVENT OWNERSHIP & STATUS
// ==============================
$stmt = $eventDB->prepare("
    SELECT event_id, status 
    FROM events 
    WHERE event_id = ? AND student_id = ?
");
$stmt->execute([$event_id, $studentId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo json_encode(["error" => "Invalid event"]);
    exit;
}

if ($event['status'] !== 'approved') {
    echo json_encode(["error" => "Attendance allowed only after event approval"]);
    exit;
}

// ==============================
// HANDLE PROOF FILE (OPTIONAL)
// ==============================
$proof_file = null;
if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === 0) {

    $uploadDir = "../uploads/attendance/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $proof_file = time() . "_" . basename($_FILES['proof_file']['name']);
    $targetPath = $uploadDir . $proof_file;

    if (!move_uploaded_file($_FILES['proof_file']['tmp_name'], $targetPath)) {
        echo json_encode(["error" => "Failed to upload proof file"]);
        exit;
    }
}

// ==============================
// PREVENT DUPLICATE ATTENDANCE
// ==============================
$check = $eventDB->prepare("
    SELECT attendance_id 
    FROM attendance 
    WHERE event_id = ? AND student_id = ?
");
$check->execute([$event_id, $studentId]);

if ($check->fetch()) {
    echo json_encode(["error" => "Attendance already submitted"]);
    exit;
}

// ==============================
// INSERT ATTENDANCE
// ==============================
$stmt = $eventDB->prepare("
    INSERT INTO attendance
    (event_id, student_id, attended, proof_file, remarks)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $event_id,
    $studentId,
    $attended,
    $proof_file,
    $remarks
]);

echo json_encode([
    "success" => true,
    "message" => "Attendance submitted and sent to TG for verification"
]);
