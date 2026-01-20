<?php

header("Access-Control-Allow-Origin: http://localhost:5500");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "college_db");

$data = json_decode(file_get_contents("php://input"), true);
$usn = $data['usn'] ?? null;

if (!$usn) {
    echo json_encode(["error" => "USN required"]);
    exit;
}

$stmt = $conn->prepare("SELECT student_id FROM students WHERE usn = ?");
$stmt->bind_param("s", $usn);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $_SESSION['student_id'] = $row['student_id'];

    echo json_encode([
        "success" => true,
        "student_id" => $row['student_id']
    ]);
} else {
    echo json_encode(["error" => "Student not found"]);
}
