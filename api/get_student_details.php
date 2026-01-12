<?php
session_start();
require "../config/college_db.php";
if (!isset($_SESSION['student_id'])) {
    echo json_encode(["error" => "Student not logged in"]);
    exit;
}
$studentId = $_SESSION['student_id'];

$stmt = $collegeDB->prepare(
  "SELECT usn, name, department_name, semester
   FROM students
   WHERE student_id = ?"
);
$stmt->execute([$studentId]);

echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
