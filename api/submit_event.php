<?php
session_start();
require "../config/event_db.php";

$studentId = $_SESSION['student_id'];
$eventId   = $_POST['event_id'];

$stmt = $eventDB->prepare(
  "INSERT INTO attendances (student_id, event_id)
   VALUES (?, ?)"
);
$stmt->execute([$studentId, $eventId]);

$attendanceId = $eventDB->lastInsertId();

$roles = ['TG','COORDINATOR','HOD','DEAN','PRINCIPAL'];

foreach ($roles as $role) {
  $eventDB->prepare(
    "INSERT INTO attendance_approvals (attendance_id, approver_role)
     VALUES (?, ?)"
  )->execute([$attendanceId, $role]);
}

echo "Attendance submitted";
