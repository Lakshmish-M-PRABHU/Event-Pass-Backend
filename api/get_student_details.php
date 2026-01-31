<?php
header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require "../config/college_db.php"; // make sure $collegeDB is correct

if (!isset($_SESSION['student_id'])) {
    echo json_encode(["error" => "Student not logged in"]);
    exit;
}

$studentId = $_SESSION['student_id'];

$stmt = $collegeDB->prepare(
  "SELECT usn, name, department, semester
   FROM students
   WHERE student_id = ?"
);
$stmt->execute([$studentId]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student) {
    echo json_encode($student);
} else {
    echo json_encode(["error" => "Student not found"]);
}

