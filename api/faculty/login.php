<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5500");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(["error" => "POST method required"]);
    exit;
}

session_start();
require "../../config/college_db.php";

$data = json_decode(file_get_contents("php://input"), true);

$name = trim($data['name'] ?? '');
$role = trim($data['role'] ?? '');

if (!$name || !$role) {
    http_response_code(400);
    echo json_encode(["error" => "Name and role required"]);
    exit;
}

$stmt = $collegeDB->prepare("
    SELECT faculty_id, name, role 
    FROM faculty 
    WHERE name = ? AND role = ?
");
$stmt->execute([$name, $role]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$faculty) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid faculty credentials"]);
    exit;
}

$_SESSION['faculty_id'] = $faculty['faculty_id'];
$_SESSION['role'] = $faculty['role'];

echo json_encode([
    "success" => true,
    "faculty" => $faculty
]);
