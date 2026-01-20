-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 20, 2026 at 10:47 AM
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
  `faculty_id` int(11) NOT NULL,
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
  `student_id` int(11) NOT NULL,
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

INSERT INTO `attendance` (`attendance_id`, `event_id`, `student_id`, `attended`, `proof_file`, `remarks`, `current_stage`, `final_status`, `created_at`) VALUES
(1, 7, 7, 'yes', NULL, NULL, 'TG', 'pending', '2026-01-14 11:48:13');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
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
  `completion_submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `student_id`, `tracking_id`, `activity_type`, `activity_name`, `date_from`, `date_to`, `activity_level`, `residency`, `event_url`, `uploaded_file`, `submission_date`, `financial_assistance`, `financial_purpose`, `financial_amount`, `approval_stage`, `status`, `attendance`, `completion_notes`, `completion_submitted_at`) VALUES
(1, 1, 'EV-476', 'Technical', 'Reading test', '2026-01-13', '2026-01-16', 'International', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-13 10:48:10', 'no', NULL, NULL, 'tg', 'pending', NULL, NULL, NULL),
(2, 1, 'EV-930', 'Technical', 'Reading test', '2026-01-13', '2026-01-16', 'International', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-13 10:48:43', 'no', NULL, NULL, 'tg', 'pending', NULL, NULL, NULL),
(3, 1, 'EV-964', 'Technical', 'Reading test', '2026-01-13', '2026-01-16', 'International', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-13 10:49:40', 'no', NULL, NULL, 'tg', 'pending', NULL, NULL, NULL),
(4, 1, 'EV-538', 'Nothing', 'Reading test ', '2026-01-13', '2026-01-13', 'National', 'Hostelite', 'https://ceatohddfoj', '1768306331_TruckHai.png', '2026-01-13 12:12:11', 'yes', 'This is for fun', 500.00, 'tg', 'pending', NULL, NULL, NULL),
(5, 4, 'EV-862', 'Non-Technical', 'Reading test', '2026-01-13', '2026-01-13', 'National', 'Hostelite', 'https://ceatohddfoj', '1768318402_TruckHai.png', '2026-01-13 15:33:22', 'yes', 'This is testing', 500.00, 'tg', 'pending', NULL, NULL, NULL),
(6, 4, 'EV-539', 'Cultural', 'Reading test', '2026-01-13', '2026-01-13', 'District', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-13 16:38:34', 'no', '', 0.00, 'tg', 'pending', NULL, NULL, NULL),
(7, 7, 'EV-562', 'Technical', 'Reading test', '2026-01-14', '2026-01-14', 'State', 'Hostelite', 'https://ceatohddfoj', NULL, '2026-01-14 09:41:36', 'no', '', 0.00, 'principal', 'approved', NULL, NULL, NULL),
(8, 7, 'EV-931', 'Technical', 'Reading test', '2026-01-14', '2026-01-14', 'National', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-14 11:08:09', 'no', '', 0.00, 'principal', 'completed', 1, NULL, '2026-01-20 13:03:51'),
(9, 7, 'EV-896', 'Cultural', 'Reading test', '2026-01-15', '2026-01-15', 'State', 'Hostelite', 'https://ceatohddfoj', '1768389634_TruckHai.png', '2026-01-14 11:20:34', 'yes', 'This is for fun', 1555.00, 'coordinator', 'rejected', NULL, NULL, NULL),
(10, 7, 'EV-778', 'Non-Technical', 'Reading test', '2026-01-15', '2026-01-15', 'National', 'Hostelite', 'https://ceatohddfoj', '1768392928_TruckHai.png', '2026-01-14 12:15:28', 'no', '', 0.00, 'coordinator', 'pending', NULL, NULL, NULL),
(11, 7, 'EV-316', 'Technical', 'Reading test', '2026-01-23', '2026-01-24', 'National', 'Hostelite', 'https://ceatohddfoj', NULL, '2026-01-14 14:31:02', 'no', '', 0.00, 'principal', 'approved', NULL, NULL, NULL),
(12, 7, 'EV-959', 'Technical', 'Reading test', '2026-01-14', '2026-01-14', 'National', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-14 15:32:02', 'no', '', 0.00, 'principal', 'completed', 1, NULL, '2026-01-14 23:47:25'),
(13, 7, 'EV-485', 'Technical', 'Reading test', '2026-01-14', '2026-01-14', 'National', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-14 15:41:25', 'yes', 'This is for testing', 500.00, 'principal', 'approved', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_approvals`
--

CREATE TABLE `event_approvals` (
  `approval_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `role` enum('TG','COORDINATOR','HOD','DEAN','PRINCIPAL') NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `action_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_approvals`
--

INSERT INTO `event_approvals` (`approval_id`, `event_id`, `faculty_id`, `role`, `status`, `remarks`, `action_date`) VALUES
(1, 1, 0, 'TG', 'approved', NULL, NULL),
(2, 1, 0, 'COORDINATOR', 'approved', NULL, NULL),
(3, 1, 0, 'HOD', 'pending', NULL, NULL),
(4, 1, 0, 'DEAN', 'pending', NULL, NULL),
(5, 1, 0, 'PRINCIPAL', 'pending', NULL, NULL),
(6, 1, 101, 'TG', 'approved', 'Good', '2026-01-13 16:17:35'),
(7, 1, 102, 'COORDINATOR', 'approved', '', '2026-01-13 16:17:35'),
(8, 1, 103, 'HOD', 'pending', '', NULL),
(9, 1, 104, 'DEAN', 'pending', '', NULL),
(10, 1, 105, 'PRINCIPAL', 'pending', '', NULL),
(11, 11, 0, 'TG', 'approved', NULL, '2026-01-14 14:32:03'),
(12, 11, 0, 'COORDINATOR', 'approved', NULL, '2026-01-14 14:51:22'),
(13, 11, 0, 'HOD', 'approved', NULL, '2026-01-14 14:53:02'),
(14, 11, 0, 'DEAN', 'approved', NULL, '2026-01-14 14:53:34'),
(15, 11, 0, 'PRINCIPAL', 'approved', NULL, '2026-01-14 14:54:18'),
(16, 12, 0, 'TG', 'approved', NULL, '2026-01-14 15:32:36'),
(17, 12, 0, 'COORDINATOR', 'approved', NULL, '2026-01-14 15:33:02'),
(18, 12, 0, 'HOD', 'approved', NULL, '2026-01-14 15:33:22'),
(19, 12, 0, 'DEAN', 'approved', NULL, '2026-01-14 15:33:42'),
(20, 12, 0, 'PRINCIPAL', 'approved', NULL, '2026-01-14 15:33:57'),
(21, 13, 0, 'TG', 'approved', NULL, '2026-01-14 15:42:53'),
(22, 13, 0, 'COORDINATOR', 'approved', NULL, '2026-01-14 15:44:07'),
(23, 13, 0, 'HOD', 'approved', NULL, '2026-01-14 15:45:00'),
(24, 13, 0, 'DEAN', 'approved', NULL, '2026-01-14 15:45:46'),
(25, 13, 0, 'PRINCIPAL', 'approved', NULL, '2026-01-14 15:46:17');

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
  `submitted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_completions`
--

INSERT INTO `event_completions` (`completion_id`, `event_id`, `experience`, `achievements`, `position`, `rating`, `submitted_at`) VALUES
(1, 12, 'This is fun ', 'This  is also fun', 'other', 5, '2026-01-14 23:47:25'),
(2, 8, 'This is fun.', 'This is fun the acheive.', '2nd', 5, '2026-01-20 13:03:51');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
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

INSERT INTO `notifications` (`notification_id`, `faculty_id`, `event_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 8, 'Attendance Confirmed', 'Student confirmed attendance for Event ID 8.', 'attendance', 0, '2026-01-20 07:30:26');

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
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

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
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `event_approvals`
--
ALTER TABLE `event_approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `event_completions`
--
ALTER TABLE `event_completions`
  MODIFY `completion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `event_completions`
--
ALTER TABLE `event_completions`
  ADD CONSTRAINT `event_completions_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
