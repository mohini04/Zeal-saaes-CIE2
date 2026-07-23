-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 23, 2026 at 07:42 AM
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
-- Database: `saaes_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_requests`
--

CREATE TABLE `access_requests` (
  `request_id` int(11) NOT NULL,
  `prn_number` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `department` varchar(100) NOT NULL,
  `status` varchar(20) DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `parent_name` varchar(100) NOT NULL DEFAULT '',
  `parent_email` varchar(150) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `access_requests`
--

INSERT INTO `access_requests` (`request_id`, `prn_number`, `full_name`, `email`, `department`, `status`, `created_at`, `parent_name`, `parent_email`) VALUES
(1, '12345678', 'aryantest1', 'aryantest1@zeal.in', 'Computer Engineering', 'REJECTED', '2026-07-21 04:56:52', '', ''),
(2, '123456789', 'aryantest2', 'aryantest@zeal.in', 'Information Technology', 'REJECTED', '2026-07-21 05:04:46', '', ''),
(3, '1234567890', 'aryantest3', 'aryantest3@zeal.in', 'Information Technology', 'REJECTED', '2026-07-21 05:07:18', '', ''),
(4, '125UEC1183', 'aryan4', 'aryan4@zeal.in', 'Mechanical Engineering', 'REJECTED', '2026-07-21 05:26:21', '', ''),
(5, '125UEC11831', 'aryan5', 'aryan5@zeal.in', 'Computer Engineering', 'REJECTED', '2026-07-21 05:45:55', 'aryan5parent', 'aryan5parent@gmail.com'),
(6, '125UEC11832', 'aryan6', 'aryan6@zeal.in', 'Computer Engineering', 'REJECTED', '2026-07-21 05:48:51', 'aryan6parent', 'aryan6p@gmail.com'),
(7, '125UEC11833', 'aryan7', 'aryan7@zeal.in', 'Computer Engineering', 'REJECTED', '2026-07-21 05:53:52', 'aryan7p', 'aryan7p@gmail.com'),
(8, '125UEC11838', 'aryan8', 'aryan8@zeal.in', 'Computer Engineering', 'REJECTED', '2026-07-21 05:57:13', 'aryan8p', 'aryan8p@gmail.com'),
(9, '125UEC1184', 'aryan01', 'aryan01@zeal.in', 'Computer Engineering', 'APPROVED', '2026-07-21 06:11:52', 'aryan01p', 'aryan01p@gmail.com'),
(10, '125UEC1185', 'aryan02', 'aryan02@zeal.in', 'Computer Engineering', 'APPROVED', '2026-07-21 06:28:05', 'aryan02p', 'aryan02p@gmail.com'),
(11, '125UEC1177', 'arju', 'arju@zeal.in', 'Computer Engineering', 'APPROVED', '2026-07-21 06:34:01', 'arjup', 'arjup@gmail.com'),
(12, '125UEC1026', 'DHANANJAY PALEKAR', 'dhananjaypalekar20@zeal.in', 'ENTC', 'APPROVED', '2026-07-21 09:25:53', 'dog123@gmail', 'dog123@gmail'),
(13, '125UEC1156', 'Batule Piyush Balu', 'batulemanisha@zeal.in', 'ENTC', 'APPROVED', '2026-07-22 05:04:54', 'Batule Balu  Nana', 'balu.batule@gmail.com'),
(14, '125UEC1140', 'aryan1', 'aryan1@zeal.in', 'Computer Engineering', 'APPROVED', '2026-07-22 09:28:06', 'aryanparent1', 'aryanparent1@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `activity_id` int(11) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date NOT NULL,
  `max_marks` tinyint(4) NOT NULL DEFAULT 5,
  `created_by` int(11) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity`
--

CREATE TABLE `activity` (
  `ActivityID` int(11) NOT NULL,
  `Subject` varchar(150) NOT NULL,
  `Unit` tinyint(4) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `DueDate` date NOT NULL,
  `MaxMarks` tinyint(4) NOT NULL DEFAULT 5,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `StudentID` int(11) NOT NULL,
  `RollNo` varchar(50) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Division` varchar(50) DEFAULT NULL,
  `ParentEmail` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submission`
--

CREATE TABLE `submission` (
  `SubmissionID` int(11) NOT NULL,
  `ActivityID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `FileName` varchar(255) NOT NULL,
  `SubmissionDate` datetime NOT NULL,
  `MarksAwarded` tinyint(4) DEFAULT NULL,
  `Status` varchar(50) DEFAULT 'Pending Review',
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Faculty','Student','Parent','GFM','HOD') NOT NULL,
  `roll_no` varchar(20) DEFAULT NULL,
  `division` varchar(10) DEFAULT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `is_first_login` tinyint(1) DEFAULT 1,
  `phone` varchar(20) DEFAULT NULL,
  `linked_student_prn` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `username`, `password`, `role`, `roll_no`, `division`, `parent_email`, `security_question`, `security_answer`, `is_first_login`, `phone`, `linked_student_prn`, `email`) VALUES
(1, 'Amit Sharma', 'stud01', 'stud123', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(2, 'Prof. Joshi', 'fac01', 'fac123', 'Faculty', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(4, 'Prof. Patil', 'gfm01', 'gfm123', 'GFM', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(5, 'Dr. Kulkarni', 'hod01', 'hod123', 'HOD', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(6, 'System Admin', 'admin01', 'admin123', 'Admin', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(8, 'aryan deshmukh', 'aryantest1', '$2y$10$V/Wtem9owinM9CVMexCQiOV72cuIv4/tkapk/zv3UAzPtoVcrdcse', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(10, 'aryantest2', 'aryantest2', '$2y$10$82nYpxeAu2eLWue5Reu9Y.J8aKvHxiuF1LKn5CXlesfUWdAjskPqu', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(12, 'aryan3', 'aryantest3', '$2y$10$8hBKqUjE7.RP4bncAExnSuVyxtI.ehTz12T4c1qsW/vntBH5YkYWi', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(13, 'piyush', 'piyush1', '$2y$10$GnoMwcQO4KpZptYX9mr8I.jPU/nDCfm58Vje.6OsIzq0.BRyN3I9y', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(14, 'faculty01', 'faculty01', '$2y$10$19bpf.zASbGJJOG9p1i3S.XE2AHIdkbwp0kspnZ/SBuLHvVJ9Inei', 'Faculty', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(15, 'naruto01', 'naruto01', '$2y$10$9VZh9Q8tMEhrUwO1bVkh2OW7tk9jlccboTfunkAP8xsxRNesJ/6g2', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(16, 'aryanadmin1', 'aryanadmin1', '$2y$10$8Y/TXC6YyYdyY8lzQJvG/O26AWJVKUiuiaA8YzEY8GEM0MtER1dn.', 'Admin', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(17, 'swami anil patil', 'swamipatil_11', '$2y$10$Ax08qFALJvKCIluA9WzPc.0XgVsCUFqYH2oC.d9DWVUk58s8kOwNK', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(18, 'sanket chandru shinde', 'sanket2112', '$2y$10$A0u6NRZAAnN2bt7u9ITF4.zSb.LyyX98zl3Qf7OYum5nFGd.gZ5kq', 'Admin', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(19, 'swami patil', 'sanket 11', '$2y$10$zXKYFeVcUjmZBcU2FBuVe.1igV5w1sRtD3TL4oLc6d5vqnFKmKPwq', 'Admin', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(20, 'admin3', 'admin3', '$2y$10$1gol.nsIA38DV6omKwfWX.w7Iw.f3EW6z/MdoqElJq4rMOcpb3v4q', 'Admin', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(21, 'aryan4', 'aryan4', '$2y$10$8r7H10XJiubze0KI81yeleyG69gjFNinHUTGMXWScS2wKYmXrQwU.', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(22, 'aryanfac1', 'aryanfac1', '$2y$10$AfYBlApYodAAUsWqCSfdue/MISXLjDfQL0ayOv24RGR7ppJSYtvSC', 'Faculty', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(24, 'System Administrator', 'admin', '$2y$10$1eRfrC87gPVMPajmAPSs2eniJbobaWQldLT3SDHFkXZmChwq.3I9.', 'Admin', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 0, NULL, NULL, NULL),
(25, 'aryantest1', '12345678', '$2y$10$VCjix8INvU5LGFH/TsBrsuAsoUTWRXiV4IJhkYcOp.P0DGQCeeutC', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(26, 'aryantest2', '123456789', '$2y$10$stJNvAJS9ybn1s3g3xvYaecILs3NO8AW3/t9A..MVxDNbfbPsnPz6', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(27, 'aryantest3', '1234567890', '$2y$10$TAO51ksnvwxUGiU/08cXEu4TkInB6Tvvl.B8j2hb/4uRfqHaO73hq', 'Student', '1234567890', 'B', NULL, 'What was the name of your first school?', 'school', 0, '', NULL, NULL),
(28, 'aryan4', '125UEC1183', '$2y$10$effao.1bw/XbGpt/U/Bx3ewejXdYODqkIJ1oeVoYc1Dt2XlnUfg9.', 'Student', '125uec1183', 'B', NULL, 'What was the name of your first school?', 'school', 0, '', NULL, NULL),
(29, 'aryan5', '125UEC11831', '$2y$10$06tQybMtgp5RSZv7jXUDRulWKNSfr/NFXDRHcSWCj/f6R/8Dz5NhK', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(31, 'aryan6', '125UEC11832', '$2y$10$kW9Brcz2dgU8cSICWDXH/uN7MvcV/49Y6VViNstafQX0arTAe8Y0u', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(33, 'aryan7', '125UEC11833', '$2y$10$LxuKM91bv5VspMsEQN5JJOPRui82nQ5IzS1dSyoZEWVYXFYSI9Izm', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, NULL),
(35, 'mahesh navhale', 'maheshn@gmail.com', '$2y$10$nq6DtEWtnErfRjUz9GWt.uMd7oFeOsSNJYsvqSbf/I6esc3ojn77e', 'HOD', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, 'maheshn@gmail.com'),
(36, 'aryan01', '125UEC1184', '$2y$10$uk3AnltEHn3D8urwUSLbkuXGRY4Fgr1nGKC5Efe/fpnhqK038rAjW', 'Student', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 1, NULL, NULL, 'aryan01@zeal.in'),
(37, 'aryan01p', 'aryan01p@gmail.com', '$2y$10$Th64on9ELjeV2tcAZoPHyuviKCkz6andP0iI1IV9Vqq.RCZjRW27G', 'Parent', NULL, NULL, NULL, 'What was the name of your first school?', 'school', 0, '', '125UEC1184', 'aryan01p@gmail.com'),
(38, 'aryan02', '125UEC1185', '$2y$10$FqqPmUU1T6Httq5YReOSKucIb.0plrsBXjkak4rhetId9yTBzeQXK', 'Student', '10', 'B', NULL, 'What was the name of your first school?', 'rose', 0, '', NULL, 'aryan02@zeal.in'),
(39, 'aryan02p', 'aryan02p@gmail.com', '$2y$10$CR3fMQNATleneTSe9OAnnObLU2pexWAh/t5teLFruM9cmQmjDs7au', 'Parent', NULL, NULL, NULL, 'What was the name of your first school?', 'rose', 0, '', '125UEC1185', 'aryan02p@gmail.com'),
(40, 'arju', '125UEC1177', '$2y$10$tlzNpNZd.BNyAmlBXqomIeAczVXJQ7z5Zs0EUcKAAvsar2pUwEuk2', 'Student', '1', 'B', NULL, 'What was the name of your first school?', 'flower', 0, '', NULL, 'arju@zeal.in'),
(41, 'arjup', 'arjup@gmail.com', '$2y$10$LmNigOphkSaiN0PKyA7afudj/s16nmsNWxNhxVZhgNopXdU3Ewi9G', 'Parent', NULL, NULL, NULL, 'What was the name of your first school?', 'flower', 0, '', '125UEC1177', 'arjup@gmail.com'),
(42, 'maheshN', 'maheshn1@gmail.com', '$2y$10$p3x74pXdhdsLw0OdmNq5DeZvKr79PvloCRc9qhyYnGPl3MRIeS436', 'HOD', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 0, '', NULL, 'maheshn1@gmail.com'),
(43, 'DHANANJAY PALEKAR', '125UEC1026', '$2y$10$5iW//A61jdi8onrZKCWyJ.dAR8a3zbKirwHvxkzfXprpl.JtlD3CS', 'Student', '13', 'B', NULL, 'What was the name of your first school?', 'aryan', 0, '', NULL, 'dhananjaypalekar20@zeal.in'),
(44, 'dog123@gmail', 'dog123@gmail', '$2y$10$8fl2N1T8PLyKl7/4o0797uasQ1scorkR7DknP3ruCzdEmsJ1HN4Iy', 'Parent', NULL, NULL, NULL, NULL, NULL, 1, NULL, '125UEC1026', 'dog123@gmail'),
(45, 'Batule Piyush Balu', '125UEC1156', '$2y$10$/tmoMUjRFW9J6YvvOuSv7OORINz1aEdQLgjQ.HUs4rRgIqClEGQP6', 'Student', '7', 'B', NULL, 'What was the name of your first pet?', 'lol', 0, '', NULL, 'batulemanisha@zeal.in'),
(46, 'Batule Balu  Nana', 'balu.batule@gmail.com', '$2y$10$E4sbJ2wK2HW.V8X8xvpEm.2R3h22xYJVmdUth6Akvn7VeDiZ7aI/O', 'Parent', NULL, NULL, NULL, 'What was the name of your first pet?', 'lol', 0, '', '125UEC1156', 'balu.batule@gmail.com'),
(47, 'faculty', 'faculty@gmail.com', '$2y$10$tSpwfNioqBKI0qqzvX76iua7jXBkzcdqtCc2.zZEpDd0rSr84b06e', 'Faculty', NULL, NULL, NULL, 'What was the name of your first school?', 'lol', 0, '', NULL, 'faculty@gmail.com'),
(48, 'hod', 'hod@gmail.com', '$2y$10$L/6yvSFSF.IF2j6rl8uk0erGflXvoH8N2a3uSs9tAzzFS6YKD8vtq', 'HOD', NULL, NULL, NULL, 'What was the name of your first school?', 'lol', 0, '', NULL, 'hod@gmail.com'),
(49, 'aryan1', '125UEC1140', '$2y$10$dqC915L/vAqC5RfMMcf/NucA1pwtbSsUC0fcF9hJoxaUjLIQZdO7K', 'Student', '40', 'B', NULL, 'What was the name of your first school?', 'zeal', 0, '', NULL, 'aryan1@zeal.in'),
(50, 'aryanparent1', 'aryanparent1@gmail.com', '$2y$10$98bCPDeXqlBnK9iSHuCTfe74HSziouq5u44djF91ktvSyYUoQzJT.', 'Parent', NULL, NULL, NULL, NULL, NULL, 1, NULL, '125UEC1140', 'aryanparent1@gmail.com'),
(51, 'faculty2', 'faculty2@gmail.com', '$2y$10$jLs0dOmAM1CPypw6EKVcpuNAXCEuaMagaonNNzrIO1ZM0OzSaswde', 'Faculty', NULL, NULL, NULL, 'What was the name of your first school?', 'zeal', 0, '', NULL, 'faculty2@gmail.com'),
(52, 'faculty3', 'faculty3@gmail.com', '$2y$10$32WJOhzNt5n5MPdsVFGPt.Oq.8DmJZnVhtpessRnjNQrqS000fc3m', 'Faculty', NULL, NULL, NULL, 'What was the name of your first school?', 'lol', 0, '', NULL, 'faculty3@gmail.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_requests`
--
ALTER TABLE `access_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `prn_number` (`prn_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`activity_id`);

--
-- Indexes for table `activity`
--
ALTER TABLE `activity`
  ADD PRIMARY KEY (`ActivityID`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`StudentID`),
  ADD UNIQUE KEY `RollNo` (`RollNo`);

--
-- Indexes for table `submission`
--
ALTER TABLE `submission`
  ADD PRIMARY KEY (`SubmissionID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`username`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_requests`
--
ALTER TABLE `access_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity`
--
ALTER TABLE `activity`
  MODIFY `ActivityID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `StudentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submission`
--
ALTER TABLE `submission`
  MODIFY `SubmissionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
