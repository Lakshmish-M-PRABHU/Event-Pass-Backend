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
$studentId = $_SESSION['studid'] ?? null;
if (!$studentId) {
    echo json_encode(["error"=>"Please login first"]);
    exit;
}

// ==============================
// DATABASE CONFIG
// ==============================
require "../config/college_db.php";
require "../config/events_db.php";
require_once "../helpers/notification_service.php";

function ensureTeamConsentTable(PDO $eventDB) {
    $eventDB->exec("
        CREATE TABLE IF NOT EXISTS team_member_consents (
            consent_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            member_id INT NOT NULL,
            studid INT NOT NULL,
            consent_status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
            responded_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_event_member (event_id, member_id),
            INDEX idx_event_studid (event_id, studid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

// ==============================
// VALIDATE STUDENT
// ==============================
$stmt = $collegeDB->prepare("SELECT * FROM students WHERE studid = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo json_encode(["error"=>"Invalid student"]);
    exit;
}

function getFacultyCode(PDO $collegeDB, string $role, ?string $department = null, ?string $activityType = null): ?int {
    if ($role === 'COORDINATOR') {
        // Prefer matching by department + activity type, then by department, then any coordinator.
        if ($department && $activityType) {
            $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? AND department = ? AND activity_type = ? LIMIT 1");
            $stmt->execute([$role, $department, $activityType]);
            $code = $stmt->fetchColumn();
            if ($code) return (int)$code;
        }
        if ($department) {
            $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? AND department = ? LIMIT 1");
            $stmt->execute([$role, $department]);
            $code = $stmt->fetchColumn();
            if ($code) return (int)$code;
        }
        $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? LIMIT 1");
        $stmt->execute([$role]);
        $code = $stmt->fetchColumn();
        return $code ? (int)$code : null;
    }

    if ($role === 'HOD') {
        if ($department) {
            $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? AND department = ? LIMIT 1");
            $stmt->execute([$role, $department]);
            $code = $stmt->fetchColumn();
            if ($code) return (int)$code;
        }
        $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? LIMIT 1");
        $stmt->execute([$role]);
        $code = $stmt->fetchColumn();
        return $code ? (int)$code : null;
    }

    // DEAN / PRINCIPAL fallback: pick the first one.
    $stmt = $collegeDB->prepare("SELECT faculty_code FROM faculty WHERE role = ? LIMIT 1");
    $stmt->execute([$role]);
    $code = $stmt->fetchColumn();
    return $code ? (int)$code : null;
}

$financialAssistance = $_POST['financial_assistance'] ?? 'no';
$financialPurposeRaw = $_POST['financial_purpose'] ?? null;
$financialAmountRaw = $_POST['financial_amount'] ?? null;

$financialPurpose = is_string($financialPurposeRaw) ? trim($financialPurposeRaw) : $financialPurposeRaw;
$financialAmount = is_string($financialAmountRaw) ? trim($financialAmountRaw) : $financialAmountRaw;

if ($financialAssistance === 'yes') {
    if ($financialPurpose === '' || $financialAmount === '' || $financialAmount === null) {
        echo json_encode(["error" => "Financial details required"]);
        exit;
    }
    if (!is_numeric($financialAmount)) {
        echo json_encode(["error" => "Financial amount must be numeric"]);
        exit;
    }
} else {
    $financialPurpose = null;
    $financialAmount = null;
}

// ==============================
// GET FORM DATA
// ==============================
$activity_type = $_POST['activity_type'] ?? null;
$event_type = $_POST['event_type'] ?? null;
$activity_name = $_POST['activity_name'] ?? null;
$date_from = $_POST['date_from'] ?? null;
$date_to = $_POST['date_to'] ?? null;
$activity_level = $_POST['activity_level'] ?? null;
$residency = $_POST['residency'] ?? null;
$event_url = $_POST['event_url'] ?? null;

// Team fields
$application_type = $_POST['application_type'] ?? 'individual';
$team_members = $_POST['team_members'] ?? null;

// Validate required fields
if (!$event_type || !$activity_type || !$activity_name || !$date_from || !$date_to || !$activity_level || !$residency) {
    echo json_encode(["error"=>"All required fields must be filled"]);
    exit;
}

// Validate team fields
if ($application_type === 'team') {
    if (empty($team_members)) {
        echo json_encode(["error"=>"Team members required"]);
        exit;
    }
    
    $members = json_decode($team_members, true);
    if (!$members || count($members) < 1) {
        echo json_encode(["error"=>"At least 1 team member required"]);
        exit;
    }
    
    // Validate each USN exists
    foreach ($members as $usn) {
        $stmt = $collegeDB->prepare("SELECT studid, name, department FROM students WHERE usn = ?");
        $stmt->execute([$usn]);
        if (!$stmt->fetch()) {
            echo json_encode(["error"=>"Student with USN $usn not found"]);
            exit;
        }
    }
}

// ==============================
// HANDLE FILE UPLOAD
// ==============================
$uploaded_file_name = null;
if (isset($_FILES['event_file']) && $_FILES['event_file']['error'] === 0) {
    $uploadsDir = "../uploads/";
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }

    $originalName = $_FILES['event_file']['name'] ?? 'upload';
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed, true)) {
        echo json_encode(["error" => "Invalid file type"]);
        exit;
    }

    $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $uploaded_file_name = time() . "_" . $baseName . "." . $ext;
    $targetPath = $uploadsDir . $uploaded_file_name;

    if (!move_uploaded_file($_FILES['event_file']['tmp_name'], $targetPath)) {
        echo json_encode(["error" => "Failed to save uploaded file"]);
        exit;
    }
}

// ==============================
// INSERT EVENT
// ==============================
$trackingId = "EV-" . rand(100,999);

try {
    ensureTeamConsentTable($eventDB);
    $eventDB->beginTransaction();
    $teamMemberContacts = [];
    
    $stmt = $eventDB->prepare("
    INSERT INTO events 
    (studid, tracking_id, activity_type, activity_name, date_from, date_to,
     activity_level, residency, event_type, event_url, uploaded_file,
     financial_assistance, financial_purpose, financial_amount, status, application_type, approval_stage)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'TG')
    ");


    $stmt->execute([
        $studentId, $trackingId, $activity_type, $activity_name, $date_from, $date_to,
        $activity_level, $residency, $event_type, $event_url, $uploaded_file_name,   
        $financialAssistance, $financialPurpose, $financialAmount, $application_type
    ]);
    
    $eventId = $eventDB->lastInsertId();

    if ($application_type === 'team') {
        // Add leader first
        $stmt = $eventDB->prepare("INSERT INTO team_members (event_id, studid, usn, name, department, is_leader) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$eventId, $studentId, $student['usn'], $student['name'], $student['department']]);
        $leaderMemberId = $eventDB->lastInsertId();
        $teamMemberContacts[] = [
            'studid' => (int)$studentId,
            'name' => $student['name'],
            'usn' => $student['usn'],
            'is_leader' => true
        ];

        $consentStmt = $eventDB->prepare("
            INSERT INTO team_member_consents (event_id, member_id, studid, consent_status, responded_at)
            VALUES (?, ?, ?, 'accepted', NOW())
        ");
        $consentStmt->execute([$eventId, $leaderMemberId, $studentId]);
        
        // Create approval flow for leader
        $roles = ['TG', 'COORDINATOR', 'HOD', 'DEAN', 'PRINCIPAL'];
        foreach ($roles as $role) {
            $stmt = $eventDB->prepare("INSERT INTO team_member_approvals (event_id, member_id, role, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$eventId, $leaderMemberId, $role]);
        }
        
        // Add other members
        foreach ($members as $usn) {
            $stmt = $collegeDB->prepare("SELECT studid, name, department FROM students WHERE usn = ?");
            $stmt->execute([$usn]);
            $memberData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($memberData['studid'] != $studentId) { // Skip leader
                $stmt = $eventDB->prepare("INSERT INTO team_members (event_id, studid, usn, name, department, is_leader) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->execute([$eventId, $memberData['studid'], $usn, $memberData['name'], $memberData['department']]);
                $memberId = $eventDB->lastInsertId();
                $teamMemberContacts[] = [
                    'studid' => (int)$memberData['studid'],
                    'name' => $memberData['name'],
                    'usn' => $usn,
                    'is_leader' => false
                ];

                $consentStmt = $eventDB->prepare("
                    INSERT INTO team_member_consents (event_id, member_id, studid, consent_status)
                    VALUES (?, ?, ?, 'pending')
                ");
                $consentStmt->execute([$eventId, $memberId, $memberData['studid']]);
                
                // Create approval flow for member
                foreach ($roles as $role) {
                    $stmt = $eventDB->prepare("INSERT INTO team_member_approvals (event_id, member_id, role, status) VALUES (?, ?, ?, 'pending')");
                    $stmt->execute([$eventId, $memberId, $role]);
                }
            }
        }
    } 
    else {
        // ==============================
        // INDIVIDUAL EVENT PROCESSING
        // ==============================
        // Resolve faculty_code for each approval role (required, NOT NULL).
        $tgStmt = $collegeDB->prepare("SELECT faculty_code FROM student_tg_mapping WHERE studid = ? AND active = 1 LIMIT 1");
        $tgStmt->execute([$studentId]);
        $tgCode = $tgStmt->fetchColumn();
        if (!$tgCode) {
            throw new PDOException("No TG mapping found for student");
        }

        $roleToFaculty = [
            'TG' => (int)$tgCode,
            'COORDINATOR' => getFacultyCode($collegeDB, 'COORDINATOR', $student['department'], $activity_type),
            'HOD' => getFacultyCode($collegeDB, 'HOD', $student['department'], null),
            'DEAN' => getFacultyCode($collegeDB, 'DEAN', null, null),
            'PRINCIPAL' => getFacultyCode($collegeDB, 'PRINCIPAL', null, null),
        ];

        foreach ($roleToFaculty as $role => $facultyCode) {
            if (!$facultyCode) {
                throw new PDOException("Faculty code not found for role: " . $role);
            }
        }

        $approvalStmt = $eventDB->prepare(
            "INSERT INTO event_approvals (event_id, faculty_code, role, status) VALUES (?, ?, ?, 'pending')"
        );

        foreach ($roleToFaculty as $role => $facultyCode) {
            $approvalStmt->execute([$eventId, $facultyCode, $role]);
        }
    }

    $eventDB->commit();

    if ($application_type === 'team') {
        $subject = "Team consent request: " . $activity_name . " (" . $trackingId . ")";
        $details = [
            "Event Name" => $activity_name,
            "Tracking ID" => $trackingId,
            "Activity Type" => $activity_type,
            "Event Type" => $event_type,
            "Dates" => $date_from . " to " . $date_to,
            "Level" => $activity_level,
            "Residency" => $residency,
            "Consent" => "Please open the Event Pass dashboard and confirm your participation."
        ];
        $html = app_build_email_html(
            "Team consent required",
            [
                "A team event has been created and requires your consent.",
                "Please review the event details and accept or reject participation from the dashboard."
            ],
            $details,
            [
                "text" => "Open Dashboard",
                "url" => "http://localhost:5501/dashboard.html"
            ]
        );
        $text = app_build_email_text(
            "Team consent required",
            [
                "A team event has been created and requires your consent.",
                "Please review the event details and accept or reject participation from the dashboard."
            ],
            $details
        );

        foreach ($teamMemberContacts as $member) {
            $recipientEmail = app_get_student_email($collegeDB, (int)$member['studid']);
            if ($recipientEmail) {
                app_send_email($recipientEmail, $subject, $html, $text);
            }
        }
    }
    
    echo json_encode([
    "success" => true,
    "tracking_id" => $trackingId,
    "application_type" => $application_type,
    "event_type" => $event_type
    ]);


} catch (PDOException $e) {
    if ($eventDB->inTransaction()) {
        $eventDB->rollBack();
    }
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
