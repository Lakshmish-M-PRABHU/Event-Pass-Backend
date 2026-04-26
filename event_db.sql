-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 26, 2026 at 11:46 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `event_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `approval_logs`
--

CREATE TABLE `approval_logs` (
  `log_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `faculty_code` int(11) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `action` enum('approved','rejected','forwarded') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `studid` int(11) NOT NULL,
  `attended` enum('yes','no') NOT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `current_stage` enum('TG','COORDINATOR','HOD','DEAN','PRINCIPAL','COMPLETED') DEFAULT 'TG',
  `final_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `event_id`, `studid`, `attended`, `proof_file`, `remarks`, `current_stage`, `final_status`, `created_at`) VALUES
(22, 56, 7, 'yes', NULL, NULL, 'TG', 'pending', '2026-04-25 07:56:09'),
(23, 56, 8, 'yes', NULL, NULL, 'TG', 'pending', '2026-04-25 07:56:09'),
(24, 56, 10, 'yes', NULL, NULL, 'TG', 'pending', '2026-04-25 07:56:09');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_approvals`
--

CREATE TABLE `attendance_approvals` (
  `approval_id` int(11) NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `studid` int(11) NOT NULL,
  `role` enum('TG','COORDINATOR','HOD','DEAN','PRINCIPAL') NOT NULL,
  `faculty_code` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `action_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_approvals`
--

INSERT INTO `attendance_approvals` (`approval_id`, `attendance_id`, `event_id`, `studid`, `role`, `faculty_code`, `status`, `remarks`, `action_date`) VALUES
(101, 22, 56, 7, 'TG', NULL, 'pending', NULL, NULL),
(102, 22, 56, 7, 'COORDINATOR', NULL, 'pending', NULL, NULL),
(103, 22, 56, 7, 'HOD', NULL, 'pending', NULL, NULL),
(104, 22, 56, 7, 'DEAN', NULL, 'pending', NULL, NULL),
(105, 22, 56, 7, 'PRINCIPAL', NULL, 'pending', NULL, NULL),
(106, 23, 56, 8, 'TG', NULL, 'pending', NULL, NULL),
(107, 23, 56, 8, 'COORDINATOR', NULL, 'pending', NULL, NULL),
(108, 23, 56, 8, 'HOD', NULL, 'pending', NULL, NULL),
(109, 23, 56, 8, 'DEAN', NULL, 'pending', NULL, NULL),
(110, 23, 56, 8, 'PRINCIPAL', NULL, 'pending', NULL, NULL),
(111, 24, 56, 10, 'TG', NULL, 'pending', NULL, NULL),
(112, 24, 56, 10, 'COORDINATOR', NULL, 'pending', NULL, NULL),
(113, 24, 56, 10, 'HOD', NULL, 'pending', NULL, NULL),
(114, 24, 56, 10, 'DEAN', NULL, 'pending', NULL, NULL),
(115, 24, 56, 10, 'PRINCIPAL', NULL, 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `studid` int(11) NOT NULL,
  `tracking_id` varchar(20) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_name` varchar(100) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `activity_level` varchar(50) NOT NULL,
  `residency` varchar(20) NOT NULL,
  `event_url` varchar(255) DEFAULT NULL,
  `uploaded_file` varchar(255) DEFAULT NULL,
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `financial_assistance` enum('yes','no') DEFAULT 'no',
  `financial_purpose` varchar(255) DEFAULT NULL,
  `financial_amount` decimal(10,2) DEFAULT NULL,
  `approval_stage` enum('tg','coordinator','hod','dean','principal','completed') DEFAULT 'tg',
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `attendance` tinyint(1) DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `completion_submitted_at` datetime DEFAULT NULL,
  `application_type` enum('individual','team') DEFAULT 'individual',
  `event_type` enum('internal','external') DEFAULT 'internal',
  `completion_reminder_sent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `studid`, `tracking_id`, `activity_type`, `activity_name`, `date_from`, `date_to`, `activity_level`, `residency`, `event_url`, `uploaded_file`, `submission_date`, `financial_assistance`, `financial_purpose`, `financial_amount`, `approval_stage`, `status`, `attendance`, `completion_notes`, `completion_submitted_at`, `application_type`, `event_type`, `completion_reminder_sent`) VALUES
(56, 7, 'EV-208', 'Technical', 'Code quest', '2026-04-24', '2026-04-24', 'National', 'Hostelite', 'https://aakriti.canaraengineering.com', NULL, '2026-04-24 08:00:51', 'yes', 'The thing is the financial part is just a problem', 600.00, 'principal', 'completed', NULL, NULL, '2026-04-26 11:30:01', 'team', 'internal', 0);

-- --------------------------------------------------------

--
-- Table structure for table `event_approvals`
--

CREATE TABLE `event_approvals` (
  `approval_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `faculty_code` int(11) NOT NULL,
  `role` enum('TG','COORDINATOR','HOD','DEAN','PRINCIPAL') NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `action_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_completions`
--

CREATE TABLE `event_completions` (
  `completion_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `experience` text NOT NULL,
  `achievements` text DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `submitted_at` datetime DEFAULT current_timestamp(),
  `certificate_files` text DEFAULT NULL,
  `photo_files` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_completions`
--

INSERT INTO `event_completions` (`completion_id`, `event_id`, `experience`, `achievements`, `position`, `rating`, `submitted_at`, `certificate_files`, `photo_files`) VALUES
(16, 56, ' y y y y y y y y y  y y y y y y y y y y  y y y y y y y y y y  y y y y y y y y y y  y y y y y y y y y y  y y y y y y y y y y ', 'ghihgaih', '2nd', 5, '2026-04-26 11:30:01', '[]', '[\"1777183201_0_Screenshot_2026-04-22_085404.png\"]');

-- --------------------------------------------------------

--
-- Table structure for table `event_completion_files`
--

CREATE TABLE `event_completion_files` (
  `file_id` int(11) NOT NULL,
  `completion_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('photo','certificate') NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `faculty_code` int(11) DEFAULT NULL,
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('attendance','completion','rejection','approval') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `faculty_code`, `event_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(42, 1, 56, 'Team Consent Completed', 'All team members confirmed participation for Code quest (Event EV-208). Approval can proceed.', 'approval', 0, '2026-04-24 08:42:54'),
(43, 2, 56, 'Team Consent Completed', 'All team members confirmed participation for Code quest (Event EV-208). Approval can proceed.', 'approval', 0, '2026-04-24 08:42:54'),
(44, 8, 56, 'Event ready for review', 'Previous stage approved. Please continue the approval flow.', 'approval', 0, '2026-04-25 07:41:13'),
(45, 9, 56, 'Event ready for review', 'Previous stage approved. Please continue the approval flow.', 'approval', 0, '2026-04-25 07:41:49'),
(46, 1, 56, 'Attendance Submitted - 1CR21CS001', 'Student Aarav Kumar (1CR21CS001) submitted attendance for Code quest (Event EV-208). Please confirm.', 'attendance', 0, '2026-04-25 07:56:09'),
(47, 1, 56, 'Attendance Submitted - 1CR21CS002', 'Student Diya Patel (1CR21CS002) submitted attendance for Code quest (Event EV-208). Please confirm.', 'attendance', 0, '2026-04-25 07:56:09'),
(48, 2, 56, 'Attendance Submitted - 1CR21CS004', 'Student Meera Iyer (1CR21CS004) submitted attendance for Code quest (Event EV-208). Please confirm.', 'attendance', 0, '2026-04-25 07:56:09');

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `member_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `studid` int(11) NOT NULL,
  `usn` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(50) NOT NULL,
  `is_leader` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`member_id`, `event_id`, `studid`, `usn`, `name`, `department`, `is_leader`) VALUES
(84, 56, 7, '1CR21CS001', 'Aarav Kumar', 'CSE', 1),
(85, 56, 8, '1CR21CS002', 'Diya Patel', 'CSE', 0),
(86, 56, 10, '1CR21CS004', 'Meera Iyer', 'ISE', 0);

-- --------------------------------------------------------

--
-- Table structure for table `team_member_approvals`
--

CREATE TABLE `team_member_approvals` (
  `approval_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `role` enum('TG','COORDINATOR','HOD','DEAN','PRINCIPAL') NOT NULL,
  `faculty_code` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `action_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_member_approvals`
--

INSERT INTO `team_member_approvals` (`approval_id`, `event_id`, `member_id`, `role`, `faculty_code`, `status`, `action_date`) VALUES
(416, 56, 84, 'TG', 1, 'approved', '2026-04-24 10:02:44'),
(417, 56, 84, 'COORDINATOR', 3, 'approved', '2026-04-24 10:04:36'),
(418, 56, 84, 'HOD', 6, 'approved', '2026-04-25 07:40:04'),
(419, 56, 84, 'DEAN', 8, 'approved', '2026-04-25 07:41:43'),
(420, 56, 84, 'PRINCIPAL', 9, 'approved', '2026-04-25 07:42:14'),
(421, 56, 85, 'TG', 1, 'approved', '2026-04-24 10:02:48'),
(422, 56, 85, 'COORDINATOR', 3, 'approved', '2026-04-24 10:04:39'),
(423, 56, 85, 'HOD', 6, 'approved', '2026-04-25 07:40:07'),
(424, 56, 85, 'DEAN', 8, 'approved', '2026-04-25 07:41:46'),
(425, 56, 85, 'PRINCIPAL', 9, 'approved', '2026-04-25 07:42:18'),
(426, 56, 86, 'TG', 2, 'approved', '2026-04-24 10:04:09'),
(427, 56, 86, 'COORDINATOR', 3, 'approved', '2026-04-24 10:04:50'),
(428, 56, 86, 'HOD', 7, 'approved', '2026-04-25 07:41:13'),
(429, 56, 86, 'DEAN', 8, 'approved', '2026-04-25 07:41:49'),
(430, 56, 86, 'PRINCIPAL', 9, 'approved', '2026-04-25 07:42:20');

-- --------------------------------------------------------

--
-- Table structure for table `team_member_consents`
--

CREATE TABLE `team_member_consents` (
  `consent_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `studid` int(11) NOT NULL,
  `consent_status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_member_consents`
--

INSERT INTO `team_member_consents` (`consent_id`, `event_id`, `member_id`, `studid`, `consent_status`, `responded_at`, `created_at`) VALUES
(67, 56, 84, 7, 'accepted', '2026-04-24 08:00:51', '2026-04-24 08:00:51'),
(68, 56, 85, 8, 'accepted', '2026-04-24 08:42:00', '2026-04-24 08:00:51'),
(69, 56, 86, 10, 'accepted', '2026-04-24 08:42:54', '2026-04-24 08:00:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approval_logs`
--
ALTER TABLE `approval_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`);

--
-- Indexes for table `attendance_approvals`
--
ALTER TABLE `attendance_approvals`
  ADD PRIMARY KEY (`approval_id`),
  ADD KEY `attendance_id` (`attendance_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `studid` (`studid`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `event_approvals`
--
ALTER TABLE `event_approvals`
  ADD PRIMARY KEY (`approval_id`);

--
-- Indexes for table `event_completions`
--
ALTER TABLE `event_completions`
  ADD PRIMARY KEY (`completion_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `event_completion_files`
--
ALTER TABLE `event_completion_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `completion_id` (`completion_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`member_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `team_member_approvals`
--
ALTER TABLE `team_member_approvals`
  ADD PRIMARY KEY (`approval_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `team_member_consents`
--
ALTER TABLE `team_member_consents`
  ADD PRIMARY KEY (`consent_id`),
  ADD UNIQUE KEY `uq_event_member` (`event_id`,`member_id`),
  ADD KEY `idx_event_studid` (`event_id`,`studid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approval_logs`
--
ALTER TABLE `approval_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `attendance_approvals`
--
ALTER TABLE `attendance_approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `event_approvals`
--
ALTER TABLE `event_approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `event_completions`
--
ALTER TABLE `event_completions`
  MODIFY `completion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `event_completion_files`
--
ALTER TABLE `event_completion_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `team_member_approvals`
--
ALTER TABLE `team_member_approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=431;

--
-- AUTO_INCREMENT for table `team_member_consents`
--
ALTER TABLE `team_member_consents`
  MODIFY `consent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_approvals`
--
ALTER TABLE `attendance_approvals`
  ADD CONSTRAINT `attendance_approvals_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`attendance_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_approvals_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

--
-- Constraints for table `event_completions`
--
ALTER TABLE `event_completions`
  ADD CONSTRAINT `event_completions_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`);

--
-- Constraints for table `event_completion_files`
--
ALTER TABLE `event_completion_files`
  ADD CONSTRAINT `event_completion_files_ibfk_1` FOREIGN KEY (`completion_id`) REFERENCES `event_completions` (`completion_id`) ON DELETE CASCADE;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `team_members_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`);

--
-- Constraints for table `team_member_approvals`
--
ALTER TABLE `team_member_approvals`
  ADD CONSTRAINT `team_member_approvals_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`),
  ADD CONSTRAINT `team_member_approvals_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `team_members` (`member_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
