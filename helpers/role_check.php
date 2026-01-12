<?php

function isTG($facultyId, $studentId, $db) {
  $stmt = $db->prepare(
    "SELECT 1 FROM student_tg_mapping
     WHERE student_id = ? AND faculty_id = ?"
  );
  $stmt->execute([$studentId, $facultyId]);
  return $stmt->fetchColumn();
}

function isHOD($facultyId, $department, $db) {
  $stmt = $db->prepare(
    "SELECT 1 FROM departments
     WHERE department_name = ?
     AND hod_faculty_id = ?"
  );
  $stmt->execute([$department, $facultyId]);
  return $stmt->fetchColumn();
}

function isCoordinator($facultyId, $activityType, $db) {
  $stmt = $db->prepare(
    "SELECT 1 FROM coordinators
     WHERE faculty_id = ?
     AND activity_type = ?"
  );
  $stmt->execute([$facultyId, $activityType]);
  return $stmt->fetchColumn();
}
