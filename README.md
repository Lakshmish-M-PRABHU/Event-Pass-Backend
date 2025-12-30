# Event-Pass-Backend
This is a Event Pass Backend for the college.


These are some database table created:
1. STUDENT TABLE
Fields:

student_id → Auto-increment primary key

usn

name

department

semester

residency → Hostelite / Day Scholar

CREATE TABLE Students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    usn VARCHAR(15) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL,
    semester INT NOT NULL,
    residency ENUM('Hostelite', 'Day Scholar') NOT NULL
);

2. FACULTY TABLE
Roles include:

Class Advisor

Dean

TG (Tutor Guardian)

Coordinator

Sports Coordinator

Technical Coordinator

Cultural Coordinator

Fields:

faculty_id → Auto-increment

name

department

role

Each faculty can have multiple students → relation stored separately (next table)

CREATE TABLE Faculty (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL,
    role ENUM(
        'Class Advisor',
        'Dean',
        'TG',
        'Coordinator',
        'Sports Coordinator',
        'Technical Coordinator',
        'Cultural Coordinator'
    ) NOT NULL
);

2.1 FACULTY–STUDENT RELATION TABLE

Because multiple students can be under multiple faculty members.

Fields:

faculty_id

student_id

CREATE TABLE Faculty_Students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    student_id INT NOT NULL,
    FOREIGN KEY (faculty_id) REFERENCES Faculty(faculty_id),
    FOREIGN KEY (student_id) REFERENCES Students(student_id)
);

3. EVENT TABLE
Fields:

event_id → primary key

student_id → foreign key (connects to Students table)

Activity Type: Technical / Cultural / Non-Technical / Sports / Others

Activity Name

Start / End Date

Event URL

Uploaded File Details (file name / path)

Level: National / International / State / District / Inter-Collegiate / Others

CREATE TABLE Events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    activity_type ENUM(
        'Technical',
        'Cultural',
        'Non-Technical',
        'Sports',
        'Other'
    ) NOT NULL,
    activity_name VARCHAR(150) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    event_url VARCHAR(255),
    upload_file VARCHAR(255),
    level ENUM(
        'National',
        'International',
        'State',
        'District',
        'Inter-Collegiate',
        'Other'
    ) NOT NULL,
    FOREIGN KEY (student_id) REFERENCES Students(student_id)
);


-- Attendances table
CREATE TABLE Attendances (
  attendance_id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  student_id INT NOT NULL,
  status ENUM('PENDING','CONFIRMED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_attendance_event FOREIGN KEY (event_id) REFERENCES Events(event_id),
  CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES Students(student_id)
);

-- AttendanceApprovals table
CREATE TABLE AttendanceApprovals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attendance_id INT NOT NULL,
  approver_role ENUM(
    'TG','HOD','Sports Coordinator','Technical Coordinator','Cultural Coordinator','Dean','Principal'
  ) NOT NULL,
  approver_faculty_id INT NULL, -- who approved (faculty_id)
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  comment VARCHAR(500),
  acted_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_attendance FOREIGN KEY (attendance_id) REFERENCES Attendances(attendance_id),
  CONSTRAINT fk_app_faculty FOREIGN KEY (approver_faculty_id) REFERENCES Faculty(faculty_id)
);

-- index for quick lookup
CREATE INDEX idx_attendance_event ON Attendances(event_id);
CREATE INDEX idx_app_attendance_status ON AttendanceApprovals(attendance_id, status);

2) Key idea / flow

Student or admin creates an attendance record for an event (student_id + event_id). Attendances.status = PENDING.

For that attendance, we create approval rows in AttendanceApprovals for each required role in sequential order, all initially PENDING. The first one is active.

When a faculty with the required role calls the approve endpoint:

We validate they are the correct next approver (role matches next PENDING row).

If this is the TG step, ensure the approving TG is actually the TG for that student (connection via Faculty_Students).

On approve: mark approval row APPROVED with timestamp, set approver_faculty_id.

Move to next approval row. If all approvals become APPROVED, set Attendances.status = CONFIRMED.

On reject: mark that approval as REJECTED, set Attendances.status = REJECTED.

Faculty can query pending approvals assigned to them (role-based) and filter by department or event.