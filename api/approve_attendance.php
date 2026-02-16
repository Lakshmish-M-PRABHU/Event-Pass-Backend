<?php
session_start();
require "../config/event_db.php";
require "../config/college_db.php";
require "../helpers/role_check.php";

$facultyId    = $_SESSION['faculty_id'];
$attendanceId = $_POST['attendance_id'];
$action       = $_POST['action']; // APPROVE / REJECT
$stmt = $eventDB->prepare(
  "SELECT a.student_id, e.activity_type
   FROM attendances a
   JOIN events e ON a.event_id = e.event_id
   WHERE a.attendance_id = ?"
);
$stmt->execute([$attendanceId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

$studentId = $data['student_id'];
$activity  = $data['activity_type'];

$stmt = $collegeDB->prepare(
  "SELECT department_name FROM students WHERE student_id = ?"
);
$stmt->execute([$studentId]);
$department = $stmt->fetchColumn();
$stmt = $eventDB->prepare(
  "SELECT * FROM attendance_approvals
   WHERE attendance_id = ?
   AND status = 'PENDING'
   ORDER BY approval_id ASC
   LIMIT 1"
);
$stmt->execute([$attendanceId]);
$approval = $stmt->fetch(PDO::FETCH_ASSOC);
$role = $approval['approver_role'];

$allowed =
  ($role === 'TG' && isTG($facultyId, $studentId, $collegeDB)) ||
  ($role === 'COORDINATOR' && isCoordinator($facultyId, $activity, $collegeDB)) ||
  ($role === 'HOD' && isHOD($facultyId, $department, $collegeDB)) ||
  ($role === 'DEAN') ||
  ($role === 'PRINCIPAL');

if (!$allowed) {
  exit("Not authorized");
}
if ($action === 'REJECT') {
  $eventDB->prepare(
    "UPDATE attendance_approvals
     SET status='REJECTED', approver_id=?
     WHERE approval_id=?"
  )->execute([$facultyId, $approval['approval_id']]);

  $eventDB->prepare(
    "UPDATE attendances SET status='REJECTED'
     WHERE attendance_id=?"
  )->execute([$attendanceId]);

  exit("Rejected");
}
$eventDB->prepare(
  "UPDATE attendance_approvals
   SET status='APPROVED', approver_id=?
   WHERE approval_id=?"
)->execute([$facultyId, $approval['approval_id']]);

echo "Approved";
