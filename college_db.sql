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
-- Database: `college_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `faculty_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('TG','COORDINATOR','HOD','DEAN','PRINCIPAL') NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `activity_type` enum('Technical','Cultural','Sports','Non-Technical','Other') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`faculty_id`, `name`, `role`, `department`, `activity_type`, `created_at`) VALUES
(1, 'Dr. Kumar', 'TG', 'CSE', NULL, '2026-01-14 09:00:21'),
(2, 'Dr. Anitha', 'TG', 'ISE', NULL, '2026-01-14 09:00:21'),
(3, 'Prof. Rajesh', 'COORDINATOR', 'CSE', 'Technical', '2026-01-14 09:00:21'),
(4, 'Prof. Sunita', 'COORDINATOR', 'CSE', 'Cultural', '2026-01-14 09:00:21'),
(5, 'Prof. Mahesh', 'COORDINATOR', 'ISE', 'Sports', '2026-01-14 09:00:21'),
(6, 'Dr. Prakash', 'HOD', 'CSE', NULL, '2026-01-14 09:00:21'),
(7, 'Dr. Suresh', 'HOD', 'ISE', NULL, '2026-01-14 09:00:21'),
(8, 'Dr. Meenakshi', 'DEAN', NULL, NULL, '2026-01-14 09:00:21'),
(9, 'Dr. Raghavan', 'PRINCIPAL', NULL, NULL, '2026-01-14 09:00:21');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `usn` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(50) NOT NULL,
  `semester` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `usn`, `name`, `department`, `semester`, `created_at`) VALUES
(7, '1CR21CS001', 'Aarav Kumar', 'CSE', 5, '2026-01-14 09:02:04'),
(8, '1CR21CS002', 'Diya Patel', 'CSE', 5, '2026-01-14 09:02:04'),
(9, '1CR21CS003', 'Rohan Singh', 'CSE', 5, '2026-01-14 09:02:04'),
(10, '1CR21CS004', 'Meera Iyer', 'ISE', 5, '2026-01-14 09:02:04'),
(11, '1CR21CS005', 'Kunal Shah', 'ISE', 5, '2026-01-14 09:02:04');

-- --------------------------------------------------------

--
-- Table structure for table `student_tg_mapping`
--

CREATE TABLE `student_tg_mapping` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `from_date` date DEFAULT NULL,
  `to_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_tg_mapping`
--

INSERT INTO `student_tg_mapping` (`id`, `student_id`, `faculty_id`, `active`, `from_date`, `to_date`) VALUES
(6, 7, 1, 1, NULL, NULL),
(7, 8, 1, 1, NULL, NULL),
(8, 9, 2, 1, NULL, NULL),
(9, 10, 2, 1, NULL, NULL),
(10, 11, 2, 1, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`faculty_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `usn` (`usn`);

--
-- Indexes for table `student_tg_mapping`
--
ALTER TABLE `student_tg_mapping`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `student_tg_mapping`
--
ALTER TABLE `student_tg_mapping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `student_tg_mapping`
--
ALTER TABLE `student_tg_mapping`
  ADD CONSTRAINT `student_tg_mapping_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `student_tg_mapping_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
