<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
session_start();
require "../config/college_db.php";
require "../config/events_db.php";

$studentId = $_SESSION['studid'] ?? null;
if (!$studentId) {
  echo json_encode(["error" => "Unauthorized"]);
  exit;
}

// Student info
$s = $collegeDB->prepare("SELECT name, usn FROM students WHERE studid=?");
$s->execute([$studentId]);
$student = $s->fetch(PDO::FETCH_ASSOC);

// Stats - events where student is leader OR team member
$statsQ = $eventDB->prepare("
  SELECT 
    COUNT(DISTINCT e.event_id) total,
    SUM(e.status='approved') approved,
    SUM(e.status='pending') pending,
    SUM(CASE WHEN e.studid = ? AND e.status='completed' THEN 1 ELSE 0 END) completed
  FROM events e
  LEFT JOIN team_members tm ON e.event_id = tm.event_id
  WHERE e.studid=? OR tm.studid=?
");
$statsQ->execute([$studentId, $studentId, $studentId]);
$stats = $statsQ->fetch(PDO::FETCH_ASSOC);

// Applications - events where student is leader OR team member
$appQ = $eventDB->prepare("
  SELECT DISTINCT e.event_id, e.tracking_id, e.activity_name, e.status, e.studid
  FROM events e
  LEFT JOIN team_members tm ON e.event_id = tm.event_id
  WHERE e.studid=? OR tm.studid=?
  ORDER BY e.submission_date DESC
");
$appQ->execute([$studentId, $studentId]);
$applications = $appQ->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  "student" => $student,
  "stats" => $stats,
  "applications" => $applications
]);
?>
