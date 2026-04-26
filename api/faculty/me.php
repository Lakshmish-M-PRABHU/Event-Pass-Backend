<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://127.0.0.1:5501");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(400);
    echo json_encode(["error" => "GET method required"]);
    exit;
}

session_start();
require "../../config/college_db.php";

$facultyCode = $_SESSION['faculty_code'] ?? null;

if (!$facultyCode) {
    http_response_code(401);
    echo json_encode(["error" => "Faculty not logged in"]);
    exit;
}

$stmt = $collegeDB->prepare("
    SELECT faculty_code, name, role, department, activity_type
    FROM faculty
    WHERE faculty_code = ?
");
$stmt->execute([$facultyCode]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$faculty) {
    http_response_code(404);
    echo json_encode(["error" => "Faculty not found"]);
    exit;
}

echo json_encode($faculty);
