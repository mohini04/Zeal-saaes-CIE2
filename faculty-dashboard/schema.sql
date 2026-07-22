-- schema.sql: Faculty Activity Database Schema for XAMPP / phpMyAdmin

CREATE DATABASE IF NOT EXISTS `faculty_activity_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `faculty_activity_db`;

-- 1. Activities Table
CREATE TABLE IF NOT EXISTS `activities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `course` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(50) NOT NULL DEFAULT 'General',
    `batch` VARCHAR(100) NOT NULL,
    `deadline` DATETIME NOT NULL,
    `total_marks` INT NOT NULL DEFAULT 100,
    `status` ENUM('Active', 'Scheduled', 'Closed') DEFAULT 'Active',
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Submissions Table
CREATE TABLE IF NOT EXISTS `submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `activity_id` INT NOT NULL,
    `student_name` VARCHAR(150) NOT NULL,
    `student_roll` VARCHAR(50) NOT NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `file_path` VARCHAR(255) NULL,
    `text_submission` TEXT NULL,
    `score` INT DEFAULT NULL,
    `feedback` TEXT NULL,
    `status` ENUM('Pending', 'Graded') DEFAULT 'Pending',
    FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Quiz Questions Table
CREATE TABLE IF NOT EXISTS `quiz_questions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `activity_id` INT NOT NULL,
    `question` TEXT NOT NULL,
    `option_a` VARCHAR(255) NOT NULL,
    `option_b` VARCHAR(255) NOT NULL,
    `option_c` VARCHAR(255) NOT NULL,
    `option_d` VARCHAR(255) NOT NULL,
    `correct_option` CHAR(1) NOT NULL,
    `points` INT DEFAULT 2,
    FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. GD Groups Table
CREATE TABLE IF NOT EXISTS `gd_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `activity_id` INT NOT NULL,
    `group_name` VARCHAR(50) NOT NULL,
    `topic` VARCHAR(255) NOT NULL,
    `slot_time` DATETIME NOT NULL,
    `venue` VARCHAR(100) DEFAULT 'Seminar Hall 102',
    FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial Sample Data Insertions featuring BEE, Chemistry, Physics, and Maths
INSERT INTO `activities` (`id`, `title`, `type`, `course`, `subject`, `batch`, `deadline`, `total_marks`, `status`, `description`) VALUES
(1, 'Circuit Analysis & AC Fundamentals Quiz', 'quiz', 'BEE101 - Basic Electrical Engineering', 'BEE', '2025-29 (Sec A & B)', '2026-07-28 23:59:00', 20, 'Active', 'Multiple choice quiz covering Kirchhoff\'s Laws, RLC Circuits, Phasor Diagrams, and 3-Phase Systems.'),
(2, 'Nanomaterials & Polymer Tech Poster', 'poster_making', 'CH101 - Engineering Chemistry', 'Chemistry', '2025-29 (All Sections)', '2026-07-30 18:00:00', 50, 'Active', 'Design an infographic poster on Industrial Water Treatment, Corrosion Control, or Synthetic Polymers.'),
(3, 'Quantum Mechanics & Lasers PPT', 'ppt', 'PH101 - Engineering Physics', 'Physics', '2025-29 (Sec C & D)', '2026-08-05 12:00:00', 30, 'Active', 'Prepare a 10-slide presentation on Fiber Optics Applications, Wave-Particle Duality, or Semiconductor Physics.'),
(4, 'Differential Equations & Calculus Case Study', 'case_study', 'MA101 - Engineering Mathematics', 'Maths', '2025-29 (Sec E)', '2026-08-02 23:59:00', 40, 'Active', 'Real-world application of Fourier Series and Laplace Transforms in signal processing and vibration analysis.'),
(5, 'Electromagnetic Field Theory GD', 'gd', 'PH101 - Engineering Physics', 'Physics', '2025-29 (Sec A)', '2026-07-29 15:00:00', 25, 'Scheduled', 'Group Discussion on Wireless Power Transfer vs High-Voltage Power Lines: Environmental Impact & Feasibility.'),
(6, 'Smart IoT & Embedded Mini Project', 'mini_project', 'BEE101 - Basic Electrical Engineering', 'BEE', '2024-28 (CSE-A)', '2026-08-15 23:59:00', 100, 'Active', 'Build an Arduino/ESP32 based Smart Energy Meter with live voltage monitoring and IoT dashboard connectivity.');
