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
$studentId = $_SESSION['studid'] ?? null; // Fixed: changed from student_id to studid
if (!$studentId) {
    echo json_encode(["error" => "Please login first"]);
    exit;
}

// ==============================
// DATABASE
// ==============================
require "../config/college_db.php";
require "../config/events_db.php";

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
// VALIDATE EVENT & GET EVENT DETAILS
// ==============================
$stmt = $eventDB->prepare("
    SELECT event_id, status, application_type, activity_type, studid
    FROM events 
    WHERE event_id = ?
");
$stmt->execute([$event_id]);
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

try {
    $eventDB->beginTransaction();

    if ($event['application_type'] === 'team') {
        // ==============================
        // TEAM EVENT ATTENDANCE SUBMISSION
        // ==============================
        
        // Verify student is the team leader
        $stmt = $eventDB->prepare("
            SELECT member_id, studid 
            FROM team_members 
            WHERE event_id = ? AND studid = ? AND is_leader = 1
        ");
        $stmt->execute([$event_id, $studentId]);
        $leader = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leader) {
            $eventDB->rollBack();
            echo json_encode(["error" => "Only team leader can submit attendance"]);
            exit;
        }

        // Get all team members
        $stmt = $eventDB->prepare("
            SELECT member_id, studid, usn, name, department 
            FROM team_members 
            WHERE event_id = ?
        ");
        $stmt->execute([$event_id]);
        $teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($teamMembers)) {
            $eventDB->rollBack();
            echo json_encode(["error" => "No team members found"]);
            exit;
        }

        // Get activity type for coordinator lookup
        $activityType = $event['activity_type'];

        // Insert attendance and create approvals for each team member
        $roles = ['TG', 'COORDINATOR', 'HOD', 'DEAN', 'PRINCIPAL'];
        
        foreach ($teamMembers as $member) {
            // Check if attendance already exists for this member
            $check = $eventDB->prepare("
                SELECT attendance_id 
                FROM attendance 
                WHERE event_id = ? AND studid = ?
            ");
            $check->execute([$event_id, $member['studid']]);
            
            if ($check->fetch()) {
                continue; // Skip if already submitted
            }

            // Insert attendance record
            $stmt = $eventDB->prepare("
                INSERT INTO attendance
                (event_id, studid, attended, proof_file, remarks, current_stage, final_status)
                VALUES (?, ?, ?, ?, ?, 'TG', 'pending')
            ");
            $stmt->execute([
                $event_id,
                $member['studid'],
                $attended,
                $proof_file, // Same proof file for all members
                $remarks
            ]);
            
            $attendanceId = $eventDB->lastInsertId();

            // Create approval records for this member
            foreach ($roles as $role) {
                $stmt = $eventDB->prepare("
                    INSERT INTO attendance_approvals
                    (attendance_id, event_id, studid, role, status)
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $attendanceId,
                    $event_id,
                    $member['studid'],
                    $role
                ]);
            }
        }

        $message = "Attendance submitted for all team members and sent for approval";

    } else {
        // ==============================
        // INDIVIDUAL EVENT ATTENDANCE SUBMISSION
        // ==============================
        
        // Verify event belongs to student
        if ($event['studid'] != $studentId) {
            $eventDB->rollBack();
            echo json_encode(["error" => "Invalid event"]);
            exit;
        }

        // Check if attendance already exists
        $check = $eventDB->prepare("
            SELECT attendance_id 
            FROM attendance 
            WHERE event_id = ? AND studid = ?
        ");
        $check->execute([$event_id, $studentId]);

        if ($check->fetch()) {
            $eventDB->rollBack();
            echo json_encode(["error" => "Attendance already submitted"]);
            exit;
        }

        // Insert attendance record
        $stmt = $eventDB->prepare("
            INSERT INTO attendance
            (event_id, studid, attended, proof_file, remarks, current_stage, final_status)
            VALUES (?, ?, ?, ?, ?, 'TG', 'pending')
        ");
        $stmt->execute([
            $event_id,
            $studentId,
            $attended,
            $proof_file,
            $remarks
        ]);

        $attendanceId = $eventDB->lastInsertId();

        // Create approval records: TG → Coordinator → HOD → Dean → Principal
        $roles = ['TG', 'COORDINATOR', 'HOD', 'DEAN', 'PRINCIPAL'];
        
        foreach ($roles as $role) {
            $stmt = $eventDB->prepare("
                INSERT INTO attendance_approvals
                (attendance_id, event_id, studid, role, status)
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $attendanceId,
                $event_id,
                $studentId,
                $role
            ]);
        }

        $message = "Attendance submitted and sent to TG for verification";
    }

    $eventDB->commit();
    
    echo json_encode([
        "success" => true,
        "message" => $message
    ]);

} catch (PDOException $e) {
    $eventDB->rollBack();
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>