# Event-Pass-Backend
This is a Event Pass Backend for the college.

We are using MYSQL and the PHP for the backend.

There are two databases 
a. College Database
b. Event Database

a. College Database(college_db):

1. Students:
CREATE TABLE students (
  student_id INT PRIMARY KEY,
  usn VARCHAR(20) UNIQUE NOT NULL,
  name VARCHAR(100),
  department_name VARCHAR(100),
  semester INT
);

Student_id is the main key to get the student details from the college database

2. faculty
CREATE TABLE faculty (
  faculty_id INT PRIMARY KEY,
  name VARCHAR(100),
  designation ENUM('FACULTY','HOD','DEAN','PRINCIPAL')
);

3. Student_tg_mapping(TG mapping)
CREATE TABLE faculty (
  faculty_id INT PRIMARY KEY,
  name VARCHAR(100),
  designation ENUM('FACULTY','HOD','DEAN','PRINCIPAL')
);

4. Department_mapping(HOD mapping)
CREATE TABLE departments (
  department_name VARCHAR(100) PRIMARY KEY,
  hod_faculty_id INT
);

5. coordinators(activity based)
CREATE TABLE coordinators (
  faculty_id INT,
  activity_type ENUM('Technical','Cultural','Sports','Non-Technical'),
  PRIMARY KEY (faculty_id, activity_type)
);

b. EVENT_DATABASE(event_db):

1. events
CREATE TABLE events (
  event_id INT AUTO_INCREMENT PRIMARY KEY,
  activity_type ENUM('Technical','Cultural','Sports','Non-Technical'),
  activity_name VARCHAR(255),
  start_date DATE,
  end_date DATE,
  event_url TEXT,
  level ENUM('College','Inter-College','State','National','International')
);

2. attendances
CREATE TABLE attendances (
  attendance_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT,
  event_id INT,
  status ENUM('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

3. attendance_approvals
CREATE TABLE attendance_approvals (
  approval_id INT AUTO_INCREMENT PRIMARY KEY,
  attendance_id INT,
  approver_role ENUM('TG','COORDINATOR','HOD','DEAN','PRINCIPAL'),
  approver_id INT NULL,
  status ENUM('PENDING','APPROVED','REJECTED','SKIPPED') DEFAULT 'PENDING',
  rejection_reason TEXT NULL
);



This  is how it is divided
event-system/
├── config/
│   ├── college_db.php
│   └── event_db.php
├── helpers/
│   └── role_check.php
├── api/
│   ├── get_student.php
│   ├── submit_event.php
│   └── approve_attendance.php
