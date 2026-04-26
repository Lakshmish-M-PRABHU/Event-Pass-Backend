<?php
header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require "../config/college_db.php";

$data = json_decode(file_get_contents("php://input"), true);
$usn = trim($data['usn'] ?? '');

if (!$usn) {
    echo json_encode(["error" => "USN required"]);
    exit;
}

$stmt = $collegeDB->prepare("SELECT usn, name, semester, department FROM students WHERE usn = ?");
$stmt->execute([$usn]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student) {
    echo json_encode(["success" => true, "student" => $student]);
} else {
    echo json_encode(["error" => "Student not found"]);
}
?>
