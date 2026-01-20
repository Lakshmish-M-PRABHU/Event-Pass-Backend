<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5500"); // Your frontend origin
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
session_start();
require "../config/college_db.php";
require "../config/events_db.php";

$studentId = $_SESSION['student_id'] ?? null;
if (!$studentId) {
  echo json_encode(["error" => "Unauthorized"]);
  exit;
}

// Student info
$s = $collegeDB->prepare("SELECT name, usn FROM students WHERE student_id=?");
$s->execute([$studentId]);
$student = $s->fetch(PDO::FETCH_ASSOC);

// Stats
$statsQ = $eventDB->prepare("
  SELECT 
    COUNT(*) total,
    SUM(status='approved') approved,
    SUM(status='pending') pending,
    SUM(status='completed') completed
  FROM events WHERE student_id=?
");
$statsQ->execute([$studentId]);
$stats = $statsQ->fetch(PDO::FETCH_ASSOC);

// Applications
$appQ = $eventDB->prepare("
  SELECT event_id, tracking_id, activity_name, status
  FROM events WHERE student_id=?
  ORDER BY submission_date DESC
");
$appQ->execute([$studentId]);
$applications = $appQ->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  "student" => $student,
  "stats" => $stats,
  "applications" => $applications
]);
