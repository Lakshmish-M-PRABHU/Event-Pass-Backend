-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 16, 2026 at 08:33 PM
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
(1, 7, 7, 'yes', NULL, NULL, 'TG', 'pending', '2026-01-14 11:48:13');

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
  `application_type` enum('individual','team') DEFAULT 'individual'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `studid`, `tracking_id`, `activity_type`, `activity_name`, `date_from`, `date_to`, `activity_level`, `residency`, `event_url`, `uploaded_file`, `submission_date`, `financial_assistance`, `financial_purpose`, `financial_amount`, `approval_stage`, `status`, `attendance`, `completion_notes`, `completion_submitted_at`, `application_type`) VALUES
(1, 1, 'EV-476', 'Technical', 'Reading test', '2026-01-13', '2026-01-16', 'International', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-13 10:48:10', 'no', NULL, NULL, 'tg', 'pending', NULL, NULL, NULL, 'individual'),
(2, 1, 'EV-930', 'Technical', 'Reading test', '2026-01-13', '2026-01-16', 'International', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-13 10:48:43', 'no', NULL, NULL, 'tg', 'pending', NULL, NULL, NULL, 'individual'),
(3, 1, 'EV-964', 'Technical', 'Reading test', '2026-01-13', '2026-01-16', 'International', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-13 10:49:40', 'no', NULL, NULL, 'tg', 'pending', NULL, NULL, NULL, 'individual'),
(4, 1, 'EV-538', 'Nothing', 'Reading test ', '2026-01-13', '2026-01-13', 'National', 'Hostelite', 'https://ceatohddfoj', '1768306331_TruckHai.png', '2026-01-13 12:12:11', 'yes', 'This is for fun', 500.00, 'tg', 'pending', NULL, NULL, NULL, 'individual'),
(5, 4, 'EV-862', 'Non-Technical', 'Reading test', '2026-01-13', '2026-01-13', 'National', 'Hostelite', 'https://ceatohddfoj', '1768318402_TruckHai.png', '2026-01-13 15:33:22', 'yes', 'This is testing', 500.00, 'tg', 'pending', NULL, NULL, NULL, 'individual'),
(6, 4, 'EV-539', 'Cultural', 'Reading test', '2026-01-13', '2026-01-13', 'District', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-13 16:38:34', 'no', '', 0.00, 'tg', 'pending', NULL, NULL, NULL, 'individual'),
(7, 7, 'EV-562', 'Technical', 'Reading test', '2026-01-14', '2026-01-14', 'State', 'Hostelite', 'https://ceatohddfoj', NULL, '2026-01-14 09:41:36', 'no', '', 0.00, 'principal', 'approved', NULL, NULL, NULL, 'individual'),
(8, 7, 'EV-931', 'Technical', 'Reading test', '2026-01-14', '2026-01-14', 'National', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-14 11:08:09', 'no', '', 0.00, 'principal', 'completed', 1, NULL, '2026-01-20 13:03:51', 'individual'),
(9, 7, 'EV-896', 'Cultural', 'Reading test', '2026-01-15', '2026-01-15', 'State', 'Hostelite', 'https://ceatohddfoj', '1768389634_TruckHai.png', '2026-01-14 11:20:34', 'yes', 'This is for fun', 1555.00, 'coordinator', 'rejected', NULL, NULL, NULL, 'individual'),
(10, 7, 'EV-778', 'Non-Technical', 'Reading test', '2026-01-15', '2026-01-15', 'National', 'Hostelite', 'https://ceatohddfoj', '1768392928_TruckHai.png', '2026-01-14 12:15:28', 'no', '', 0.00, 'coordinator', 'pending', NULL, NULL, NULL, 'individual'),
(11, 7, 'EV-316', 'Technical', 'Reading test', '2026-01-23', '2026-01-24', 'National', 'Hostelite', 'https://ceatohddfoj', NULL, '2026-01-14 14:31:02', 'no', '', 0.00, 'principal', 'approved', NULL, NULL, NULL, 'individual'),
(12, 7, 'EV-959', 'Technical', 'Reading test', '2026-01-14', '2026-01-14', 'National', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-14 15:32:02', 'no', '', 0.00, 'principal', 'completed', 1, NULL, '2026-01-14 23:47:25', 'individual'),
(13, 7, 'EV-485', 'Technical', 'Reading test', '2026-01-14', '2026-01-14', 'National', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-01-14 15:41:25', 'yes', 'This is for testing', 500.00, 'principal', 'approved', 1, NULL, NULL, 'individual'),
(14, 7, 'EV-832', 'Technical', 'Reading test', '2026-01-20', '2026-01-20', 'Zonal', 'Hostelite', 'https://ceatohddfoj', '1768909389_TruckHai.png', '2026-01-20 11:43:09', 'no', '', 0.00, 'principal', 'completed', 1, NULL, '2026-01-20 17:20:37', 'individual'),
(15, 7, 'EV-855', 'Technical', 'Reading test', '2026-01-21', '2026-01-21', 'International', 'Day Scholar', 'https://ceatohddfoj', '1768934153_TruckHai.png', '2026-01-20 18:35:53', 'yes', 'This is for fun.', 500.00, 'principal', 'completed', 1, NULL, '2026-01-21 10:56:29', 'individual'),
(16, 7, 'EV-156', 'Technical', 'Reading test', '2026-01-21', '2026-01-21', 'National', 'Hostelite', 'https://ceatohddfoj', '1768973735_Truck_Hai.jpeg', '2026-01-21 05:35:35', 'yes', 'this is financial', 500.00, 'principal', 'completed', 1, NULL, '2026-01-21 11:10:00', 'individual'),
(17, 7, 'EV-335', 'Technical', 'Reading test', '2026-01-21', '2026-01-21', 'National', 'Day Scholar', 'https://ceatohddfoj', '1768975184_Truck_Hai.jpeg', '2026-01-21 05:59:44', 'yes', 'This', 500.00, 'principal', 'approved', 1, NULL, NULL, 'individual'),
(18, 7, 'EV-981', 'Cultural', 'Group dance', '2026-01-22', '2026-01-22', 'State', 'Day Scholar', '', '1768976447_Truck_Hai.jpeg', '2026-01-21 06:20:47', 'no', '', 0.00, 'principal', 'approved', NULL, NULL, NULL, 'individual'),
(19, 7, 'EV-272', 'Technical', 'Reading test', '2026-01-28', '2026-01-28', 'District', 'Day Scholar', 'https://ceatohddfoj', '1769591215_Truck_Hai.jpeg', '2026-01-28 09:06:55', 'yes', 'dhshfi', 500.00, 'principal', 'completed', 1, NULL, '2026-01-28 14:45:18', 'individual'),
(20, 7, 'EV-629', 'Technical', 'Reading test', '2026-02-05', '2026-02-05', 'National', 'Hostelite', 'https://ceatohddfoj', '1770308790_Truck_Hai.jpeg', '2026-02-05 16:26:30', 'no', '', 0.00, 'tg', 'pending', NULL, NULL, NULL, 'team'),
(21, 9, 'EV-322', 'Technical', 'Reading test', '2026-02-05', '2026-02-05', 'National', 'Day Scholar', 'https://ceatohddfoj', NULL, '2026-02-05 18:11:06', 'no', '', 0.00, 'tg', 'pending', NULL, NULL, NULL, 'team'),
(22, 9, 'EV-295', 'Cultural', 'kjfsdf', '2026-02-06', '2026-02-06', 'Zonal', 'Hostelite', '', '1770374898_Truck_Hai.jpeg', '2026-02-06 10:48:18', 'no', '', 0.00, 'tg', 'pending', NULL, NULL, NULL, 'team'),
(23, 9, 'EV-371', 'Technical', 'Practice 1', '2026-02-06', '2026-02-06', 'National', 'Day Scholar', 'https://ceatohddfoj', '1770390119_Truck_Hai.jpeg', '2026-02-06 15:01:59', 'no', '', 0.00, 'tg', 'pending', NULL, NULL, NULL, 'team');

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

--
-- Dumping data for table `event_approvals`
--

INSERT INTO `event_approvals` (`approval_id`, `event_id`, `faculty_code`, `role`, `status`, `remarks`, `action_date`) VALUES
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
(25, 13, 0, 'PRINCIPAL', 'approved', NULL, '2026-01-14 15:46:17'),
(26, 14, 0, 'TG', 'approved', NULL, '2026-01-20 11:43:34'),
(27, 14, 0, 'COORDINATOR', 'approved', NULL, '2026-01-20 11:44:01'),
(28, 14, 0, 'HOD', 'approved', NULL, '2026-01-20 11:44:23'),
(29, 14, 0, 'DEAN', 'approved', NULL, '2026-01-20 11:44:49'),
(30, 14, 0, 'PRINCIPAL', 'approved', NULL, '2026-01-20 11:45:13'),
(31, 15, 0, 'TG', 'approved', NULL, '2026-01-20 18:48:53'),
(32, 15, 0, 'COORDINATOR', 'approved', NULL, '2026-01-20 18:52:14'),
(33, 15, 0, 'HOD', 'approved', NULL, '2026-01-20 18:52:35'),
(34, 15, 0, 'DEAN', 'approved', NULL, '2026-01-20 18:53:07'),
(35, 15, 0, 'PRINCIPAL', 'approved', NULL, '2026-01-20 18:53:30'),
(36, 16, 0, 'TG', 'approved', NULL, '2026-01-21 05:36:13'),
(37, 16, 0, 'COORDINATOR', 'approved', NULL, '2026-01-21 05:36:41'),
(38, 16, 0, 'HOD', 'approved', NULL, '2026-01-21 05:37:16'),
(39, 16, 0, 'DEAN', 'approved', NULL, '2026-01-21 05:37:47'),
(40, 16, 0, 'PRINCIPAL', 'approved', NULL, '2026-01-21 05:38:13'),
(41, 17, 0, 'TG', 'approved', NULL, '2026-01-21 06:00:51'),
(42, 17, 0, 'COORDINATOR', 'approved', NULL, '2026-01-21 06:03:24'),
(43, 17, 0, 'HOD', 'approved', NULL, '2026-01-21 06:03:46'),
(44, 17, 0, 'DEAN', 'approved', NULL, '2026-01-21 06:04:11'),
(45, 17, 0, 'PRINCIPAL', 'approved', NULL, '2026-01-21 06:04:29'),
(46, 18, 0, 'TG', 'approved', NULL, '2026-01-21 06:23:25'),
(47, 18, 0, 'COORDINATOR', 'approved', NULL, '2026-01-21 06:24:18'),
(48, 18, 0, 'HOD', 'approved', NULL, '2026-01-21 06:25:14'),
(49, 18, 0, 'DEAN', 'approved', NULL, '2026-01-21 06:25:55'),
(50, 18, 0, 'PRINCIPAL', 'approved', NULL, '2026-01-21 06:26:12'),
(51, 19, 0, 'TG', 'approved', NULL, '2026-01-28 09:12:28'),
(52, 19, 0, 'COORDINATOR', 'approved', NULL, '2026-01-28 09:13:12'),
(53, 19, 0, 'HOD', 'approved', NULL, '2026-01-28 09:13:34'),
(54, 19, 0, 'DEAN', 'approved', NULL, '2026-01-28 09:13:54'),
(55, 19, 0, 'PRINCIPAL', 'approved', NULL, '2026-01-28 09:14:09');

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
(2, 8, 'This is fun.', 'This is fun the acheive.', '2nd', 5, '2026-01-20 13:03:51'),
(3, 14, 'THis is error ', 'checking ', '1st', 5, '2026-01-20 17:20:37'),
(4, 15, 'This is fun ', 'This is some things.', '1st', 5, '2026-01-21 10:56:29'),
(5, 16, 'This was good\r\n', 'Nothihng', 'other', 5, '2026-01-21 11:10:00'),
(6, 19, 'uu', 'iji', '1st', 5, '2026-01-28 14:45:18');

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
(1, 1, 8, 'Attendance Confirmed', 'Student confirmed attendance for Event ID 8.', 'attendance', 0, '2026-01-20 07:30:26'),
(2, 1, 14, 'Event Approved', 'Attendance approved for Event ID 14.', 'approval', 0, '2026-01-20 11:45:13'),
(3, 1, 14, 'Attendance Marked', 'Student has marked attendance for Event ID 14.', 'attendance', 0, '2026-01-20 11:49:56'),
(4, 1, 15, 'Event Approved', 'Attendance approved for Event ID 15.', 'approval', 0, '2026-01-20 18:53:30'),
(5, 1, 15, 'Attendance Marked', 'Student has marked attendance for Event ID 15.', 'attendance', 0, '2026-01-21 05:25:05'),
(6, 1, 16, 'Event Approved', 'Attendance approved for Event ID 16.', 'approval', 0, '2026-01-21 05:38:13'),
(7, 1, 16, 'Attendance Marked', 'Student has marked attendance for Event ID 16.', 'attendance', 0, '2026-01-21 05:39:09'),
(8, 1, 17, 'Event Approved', 'Attendance approved for Event ID 17.', 'approval', 0, '2026-01-21 06:04:29'),
(9, 1, 17, 'Attendance Marked', 'Student has marked attendance for Event ID 17.', 'attendance', 0, '2026-01-21 06:04:45'),
(10, 1, 18, 'Event Approved', 'Attendance approved for Event ID 18.', 'approval', 0, '2026-01-21 06:26:12'),
(11, 1, 19, 'Event Approved', 'Attendance approved for Event ID 19.', 'approval', 0, '2026-01-28 09:14:09'),
(12, 1, 19, 'Attendance Marked', 'Student has marked attendance for Event ID 19.', 'attendance', 0, '2026-01-28 09:14:16');

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
(1, 20, 7, '1CR21CS001', 'Aarav Kumar', 'CSE', 1),
(2, 20, 8, '1CR21CS002', 'Diya Patel', 'CSE', 0),
(3, 20, 9, '1CR21CS003', 'Rohan Singh', 'CSE', 0),
(4, 21, 9, '1CR21CS003', 'Rohan Singh', 'CSE', 1),
(5, 21, 8, '1CR21CS002', 'Diya Patel', 'CSE', 0),
(6, 22, 9, '1CR21CS003', 'Rohan Singh', 'CSE', 1),
(7, 22, 8, '1CR21CS002', 'Diya Patel', 'CSE', 0),
(8, 22, 10, '1CR21CS004', 'Meera Iyer', 'ISE', 0),
(9, 23, 9, '1CR21CS003', 'Rohan Singh', 'CSE', 1),
(10, 23, 7, '1CR21CS001', 'Aarav Kumar', 'CSE', 0),
(11, 23, 8, '1CR21CS002', 'Diya Patel', 'CSE', 0),
(12, 23, 10, '1CR21CS004', 'Meera Iyer', 'ISE', 0);

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
(1, 20, 1, 'TG', 1, 'approved', '2026-02-05 17:38:56'),
(2, 20, 1, 'COORDINATOR', NULL, 'pending', NULL),
(3, 20, 1, 'HOD', NULL, 'pending', NULL),
(4, 20, 1, 'DEAN', NULL, 'pending', NULL),
(5, 20, 1, 'PRINCIPAL', NULL, 'pending', NULL),
(6, 20, 2, 'TG', 1, 'approved', '2026-02-05 17:38:56'),
(7, 20, 2, 'COORDINATOR', NULL, 'pending', NULL),
(8, 20, 2, 'HOD', NULL, 'pending', NULL),
(9, 20, 2, 'DEAN', NULL, 'pending', NULL),
(10, 20, 2, 'PRINCIPAL', NULL, 'pending', NULL),
(11, 20, 3, 'TG', NULL, 'pending', NULL),
(12, 20, 3, 'COORDINATOR', NULL, 'pending', NULL),
(13, 20, 3, 'HOD', NULL, 'pending', NULL),
(14, 20, 3, 'DEAN', NULL, 'pending', NULL),
(15, 20, 3, 'PRINCIPAL', NULL, 'pending', NULL),
(16, 21, 4, 'TG', NULL, 'pending', NULL),
(17, 21, 4, 'COORDINATOR', NULL, 'pending', NULL),
(18, 21, 4, 'HOD', NULL, 'pending', NULL),
(19, 21, 4, 'DEAN', NULL, 'pending', NULL),
(20, 21, 4, 'PRINCIPAL', NULL, 'pending', NULL),
(21, 21, 5, 'TG', 1, 'approved', '2026-02-05 18:34:51'),
(22, 21, 5, 'COORDINATOR', NULL, 'pending', NULL),
(23, 21, 5, 'HOD', NULL, 'pending', NULL),
(24, 21, 5, 'DEAN', NULL, 'pending', NULL),
(25, 21, 5, 'PRINCIPAL', NULL, 'pending', NULL),
(26, 22, 6, 'TG', NULL, 'pending', NULL),
(27, 22, 6, 'COORDINATOR', NULL, 'pending', NULL),
(28, 22, 6, 'HOD', NULL, 'pending', NULL),
(29, 22, 6, 'DEAN', NULL, 'pending', NULL),
(30, 22, 6, 'PRINCIPAL', NULL, 'pending', NULL),
(31, 22, 7, 'TG', NULL, 'pending', NULL),
(32, 22, 7, 'COORDINATOR', NULL, 'pending', NULL),
(33, 22, 7, 'HOD', NULL, 'pending', NULL),
(34, 22, 7, 'DEAN', NULL, 'pending', NULL),
(35, 22, 7, 'PRINCIPAL', NULL, 'pending', NULL),
(36, 22, 8, 'TG', NULL, 'pending', NULL),
(37, 22, 8, 'COORDINATOR', NULL, 'pending', NULL),
(38, 22, 8, 'HOD', NULL, 'pending', NULL),
(39, 22, 8, 'DEAN', NULL, 'pending', NULL),
(40, 22, 8, 'PRINCIPAL', NULL, 'pending', NULL),
(41, 23, 9, 'TG', NULL, 'pending', NULL),
(42, 23, 9, 'COORDINATOR', NULL, 'pending', NULL),
(43, 23, 9, 'HOD', NULL, 'pending', NULL),
(44, 23, 9, 'DEAN', NULL, 'pending', NULL),
(45, 23, 9, 'PRINCIPAL', NULL, 'pending', NULL),
(46, 23, 10, 'TG', NULL, 'pending', NULL),
(47, 23, 10, 'COORDINATOR', NULL, 'pending', NULL),
(48, 23, 10, 'HOD', NULL, 'pending', NULL),
(49, 23, 10, 'DEAN', NULL, 'pending', NULL),
(50, 23, 10, 'PRINCIPAL', NULL, 'pending', NULL),
(51, 23, 11, 'TG', NULL, 'pending', NULL),
(52, 23, 11, 'COORDINATOR', NULL, 'pending', NULL),
(53, 23, 11, 'HOD', NULL, 'pending', NULL),
(54, 23, 11, 'DEAN', NULL, 'pending', NULL),
(55, 23, 11, 'PRINCIPAL', NULL, 'pending', NULL),
(56, 23, 12, 'TG', NULL, 'pending', NULL),
(57, 23, 12, 'COORDINATOR', NULL, 'pending', NULL),
(58, 23, 12, 'HOD', NULL, 'pending', NULL),
(59, 23, 12, 'DEAN', NULL, 'pending', NULL),
(60, 23, 12, 'PRINCIPAL', NULL, 'pending', NULL);

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
-- AUTO_INCREMENT for table `attendance_approvals`
--
ALTER TABLE `attendance_approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `event_approvals`
--
ALTER TABLE `event_approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `event_completions`
--
ALTER TABLE `event_completions`
  MODIFY `completion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `team_member_approvals`
--
ALTER TABLE `team_member_approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

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
