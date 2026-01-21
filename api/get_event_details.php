<?php
header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

session_start();
require "../config/events_db.php";

$input = json_decode(file_get_contents('php://input'), true);
$eventId = $input['event_id'] ?? null;
$studentId = $_SESSION['student_id'] ?? 1;

// 1️⃣ Get event
if ($eventId) {
    $stmt = $eventDB->prepare("SELECT * FROM events WHERE event_id = ? AND student_id = ?");
    $stmt->execute([$eventId, $studentId]);
} else {
    $stmt = $eventDB->prepare("SELECT * FROM events WHERE student_id = ? ORDER BY submission_date DESC LIMIT 1");
    $stmt->execute([$studentId]);
}

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo json_encode(["error" => "Event not found"]);
    exit;
}

// 2️⃣ Get existing approvals
$stmt = $eventDB->prepare("SELECT role, status, action_date FROM event_approvals WHERE event_id = ?");
$stmt->execute([$event['event_id']]);
$approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3️⃣ Define full approval flow
$flow = ['TG', 'COORDINATOR', 'HOD', 'DEAN', 'PRINCIPAL'];

// 4️⃣ Map approvals to full flow
$fullApprovals = [];
foreach ($flow as $role) {
    $found = array_filter($approvals, fn($a) => strtoupper($a['role']) === $role);
    if ($found) {
        $a = array_values($found)[0];
        $fullApprovals[] = [
            "role" => $role,
            "status" => strtolower($a['status']),
            "date" => $a['action_date']
        ];
    } else {
        $fullApprovals[] = [
            "role" => $role,
            "status" => "pending",
            "date" => null
        ];
    }
}

// 5️⃣ Check if attendance can be shown
$allApproved = !empty($approvals) && count(array_filter($approvals, fn($a) => $a['status'] !== 'approved')) === 0;

echo json_encode([
    "event" => $event,
    "approvals" => $fullApprovals,
    "showAttendance" => $allApproved && $event['status'] === 'completed'
]);
