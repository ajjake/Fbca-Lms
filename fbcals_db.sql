-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 27, 2026 at 01:36 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fbcals_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `exam_requests`
--

CREATE TABLE `exam_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `request_type` enum('lesson_exam','quarter_exam') NOT NULL,
  `quarter` int(11) DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_requests`
--

INSERT INTO `exam_requests` (`id`, `student_id`, `lesson_id`, `request_type`, `quarter`, `status`, `remarks`, `requested_at`, `reviewed_at`, `reviewed_by`) VALUES
(8, 4, 21, 'lesson_exam', NULL, 'approved', '', '2026-02-18 08:28:53', '2026-02-18 08:29:10', 1);

-- --------------------------------------------------------

--
-- Table structure for table `final_average_requests`
--

CREATE TABLE `final_average_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `quarter` int(11) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `final_average_requests`
--

INSERT INTO `final_average_requests` (`id`, `student_id`, `quarter`, `status`, `remarks`, `requested_at`, `reviewed_at`, `reviewed_by`) VALUES
(1, 4, 1, 'approved', '', '2026-02-24 05:51:01', '2026-02-24 05:51:26', 1);

-- --------------------------------------------------------

--
-- Table structure for table `final_grades`
--

CREATE TABLE `final_grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `q1_grade` decimal(5,2) DEFAULT 0.00,
  `q2_grade` decimal(5,2) DEFAULT 0.00,
  `q3_grade` decimal(5,2) DEFAULT 0.00,
  `q4_grade` decimal(5,2) DEFAULT 0.00,
  `final_average` decimal(5,2) DEFAULT 0.00,
  `computed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `final_grades`
--

INSERT INTO `final_grades` (`id`, `student_id`, `subject_id`, `q1_grade`, `q2_grade`, `q3_grade`, `q4_grade`, `final_average`, `computed_at`) VALUES
(7, 4, 3, 100.00, 0.00, 0.00, 0.00, 100.00, '2026-02-18 08:29:44');

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `lesson_number` varchar(20) NOT NULL,
  `pace_number` varchar(20) DEFAULT NULL,
  `pace_type` enum('lesson','monthly_test','quarter_test') DEFAULT 'lesson',
  `monthly_test_id` int(11) DEFAULT NULL,
  `quarter_test_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `quarter` int(11) NOT NULL CHECK (`quarter` between 1 and 4),
  `level` int(11) NOT NULL,
  `video_url` text DEFAULT NULL,
  `video_file` varchar(255) DEFAULT NULL,
  `material_file` varchar(255) DEFAULT NULL,
  `image_file` varchar(255) DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `subject_id`, `lesson_number`, `pace_number`, `pace_type`, `monthly_test_id`, `quarter_test_id`, `title`, `description`, `content`, `quarter`, `level`, `video_url`, `video_file`, `material_file`, `image_file`, `order_index`, `created_at`, `updated_at`) VALUES
(18, 1, 'ENG 1013', 'ENG 1013', 'lesson', NULL, NULL, 'English PACE 1013', NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, 0, '2026-02-18 08:24:24', '2026-02-18 08:24:24'),
(19, 1, 'ENG 1014', 'ENG 1014', 'lesson', NULL, NULL, 'English PACE 1014', NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, 1, '2026-02-18 08:24:24', '2026-02-18 08:24:24'),
(20, 1, 'ENG 1015', 'ENG 1015', 'lesson', NULL, NULL, 'English PACE 1015', NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, 2, '2026-02-18 08:24:24', '2026-02-18 08:24:24'),
(21, 3, 'SCI 1096', 'SCI 1096', 'lesson', NULL, NULL, 'Science PACE 1096', NULL, NULL, 1, 10, NULL, NULL, NULL, NULL, 0, '2026-02-18 08:26:36', '2026-02-18 08:26:36'),
(22, 3, 'SCI 1097', 'SCI 1097', 'lesson', NULL, NULL, 'Science PACE 1097', NULL, NULL, 1, 10, NULL, NULL, NULL, NULL, 1, '2026-02-18 08:26:36', '2026-02-18 08:26:36'),
(23, 3, 'SCI 1098', 'SCI 1098', 'lesson', NULL, NULL, 'Science PACE 1098', NULL, NULL, 1, 10, NULL, NULL, NULL, NULL, 2, '2026-02-18 08:26:36', '2026-02-18 08:26:36');

-- --------------------------------------------------------

--
-- Table structure for table `lesson_scores`
--

CREATE TABLE `lesson_scores` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `total_points` decimal(5,2) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `passed` tinyint(1) DEFAULT 0,
  `attempt_number` int(11) DEFAULT 1,
  `is_best_score` tinyint(1) DEFAULT 0,
  `time_taken` int(11) DEFAULT 0,
  `taken_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lesson_scores`
--

INSERT INTO `lesson_scores` (`id`, `student_id`, `quiz_id`, `lesson_id`, `score`, `total_points`, `percentage`, `passed`, `attempt_number`, `is_best_score`, `time_taken`, `taken_at`) VALUES
(20, 4, 10, 21, 0.00, 1.00, 0.00, 0, 1, 0, 0, '2026-02-18 08:29:33'),
(21, 4, 10, 21, 1.00, 1.00, 100.00, 1, 2, 1, 0, '2026-02-18 08:29:44');

-- --------------------------------------------------------

--
-- Table structure for table `quarter_exams`
--

CREATE TABLE `quarter_exams` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `quarter` int(11) NOT NULL CHECK (`quarter` between 1 and 4),
  `level` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `passing_score` decimal(5,2) DEFAULT 75.00,
  `time_limit` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quarter_exam_questions`
--

CREATE TABLE `quarter_exam_questions` (
  `id` int(11) NOT NULL,
  `quarter_exam_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `question_type` enum('multiple_choice','true_false') NOT NULL,
  `option_a` varchar(255) DEFAULT NULL,
  `option_b` varchar(255) DEFAULT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `correct_answer` varchar(10) NOT NULL,
  `points` decimal(5,2) DEFAULT 1.00,
  `order_index` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quarter_exam_scores`
--

CREATE TABLE `quarter_exam_scores` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `quarter_exam_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `quarter` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `total_points` decimal(5,2) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `passed` tinyint(1) DEFAULT 0,
  `taken_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quarter_grades`
--

CREATE TABLE `quarter_grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `quarter` int(11) NOT NULL CHECK (`quarter` between 1 and 4),
  `lesson_average` decimal(5,2) DEFAULT 0.00,
  `quarter_exam_score` decimal(5,2) DEFAULT 0.00,
  `final_grade` decimal(5,2) DEFAULT 0.00,
  `computed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quarter_grades`
--

INSERT INTO `quarter_grades` (`id`, `student_id`, `subject_id`, `quarter`, `lesson_average`, `quarter_exam_score`, `final_grade`, `computed_at`) VALUES
(20, 4, 3, 1, 100.00, 0.00, 100.00, '2026-02-18 08:29:44');

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `passing_score` decimal(5,2) DEFAULT 75.00,
  `time_limit` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `lesson_id`, `title`, `passing_score`, `time_limit`, `created_at`, `updated_at`) VALUES
(9, 18, 'Pace Test', 85.00, 30, '2026-02-18 08:25:11', '2026-02-18 08:25:11'),
(10, 21, 'Test', 75.00, 30, '2026-02-18 08:27:04', '2026-02-18 08:27:04');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `question_type` enum('multiple_choice','true_false') NOT NULL,
  `option_a` varchar(255) DEFAULT NULL,
  `option_b` varchar(255) DEFAULT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `correct_answer` varchar(10) NOT NULL,
  `points` decimal(5,2) DEFAULT 1.00,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `quiz_id`, `question`, `question_type`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `points`, `order_index`, `created_at`) VALUES
(15, 9, '1 + 1', 'multiple_choice', '2', '4', '3', '5', 'A', 1.00, 0, '2026-02-18 08:25:42'),
(16, 10, 'Hi', 'multiple_choice', '1', '2', '3', '4', 'A', 1.00, 0, '2026-02-18 08:27:19');

-- --------------------------------------------------------

--
-- Table structure for table `student_progress`
--

CREATE TABLE `student_progress` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `status` enum('locked','unlocked','in_progress','completed') DEFAULT 'locked',
  `unlocked_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_progress`
--

INSERT INTO `student_progress` (`id`, `student_id`, `lesson_id`, `status`, `unlocked_at`, `completed_at`) VALUES
(58, 4, 18, 'unlocked', '2026-02-18 08:28:21', NULL),
(59, 4, 19, 'unlocked', '2026-02-18 08:28:21', NULL),
(60, 4, 20, 'unlocked', '2026-02-18 08:28:21', NULL),
(61, 4, 21, 'completed', '2026-02-18 08:29:10', '2026-02-18 08:29:44'),
(62, 4, 22, 'unlocked', '2026-02-18 08:29:44', NULL),
(63, 4, 23, 'unlocked', '2026-02-18 08:28:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`, `code`, `description`, `created_at`) VALUES
(1, 'English', 'ENG', NULL, '2026-01-22 05:50:46'),
(2, 'World Building', 'WB', NULL, '2026-01-22 05:50:46'),
(3, 'Science', 'SCI', NULL, '2026-01-22 05:50:46'),
(4, 'Filipino', 'FIL', NULL, '2026-01-22 05:50:46'),
(5, 'Araling Panlipunan', 'AP', NULL, '2026-01-22 05:50:46'),
(6, 'Mathematics', 'MATH', NULL, '2026-01-22 05:50:46'),
(7, 'Computer', 'COMP', NULL, '2026-01-22 05:50:46');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_levels`
--

CREATE TABLE `teacher_levels` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_levels`
--

INSERT INTO `teacher_levels` (`id`, `teacher_id`, `level`) VALUES
(2, 5, 10);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subjects`
--

CREATE TABLE `teacher_subjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('student','teacher','admin') NOT NULL DEFAULT 'student',
  `level` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `avatar` varchar(255) DEFAULT NULL,
  `lrn` varchar(64) DEFAULT NULL,
  `guardian_name` varchar(255) DEFAULT NULL,
  `guardian_contact` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `email`, `role`, `level`, `created_at`, `updated_at`, `avatar`, `lrn`, `guardian_name`, `guardian_contact`) VALUES
(1, 'admin', '$2y$10$6k0h.KKTRRqCel2Qrpj6Ae2h/c2V.FWoGBKPR7WLrZj8VTetBYkNC', 'System Administrator', 'admin@fbcals.com', 'admin', 1, '2026-01-22 05:50:46', '2026-01-22 05:53:38', NULL, NULL, NULL, NULL),
(4, 'Jake', '$2y$10$zmwzCOTevnB3z4jRLuBdheGT9c8DtB4C5N5woAvwM0yQmElXe7IV2', 'Jay Jongseong Park', 'jay@gmail.com', 'student', 10, '2026-02-02 06:48:16', '2026-02-24 06:41:33', 'uploads/images/avatars/4_1771914086.jpg', '1181816231236', 'Sandara Park', '09090912333'),
(5, 'Charm', '$2y$10$nS0H4Hayjs3Pf/gJfWzete1vI6h19QlQLqAnHS0a98sh5kcJBWPVG', 'Charm Caroline O. Malacad', 'charmomalacad@gmail.com', 'teacher', 1, '2026-02-24 06:04:53', '2026-02-24 06:32:13', 'uploads/images/avatars/5_1771914733.jpg', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `exam_requests`
--
ALTER TABLE `exam_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `final_average_requests`
--
ALTER TABLE `final_average_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_quarter` (`student_id`,`quarter`);

--
-- Indexes for table `final_grades`
--
ALTER TABLE `final_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject` (`student_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lesson_number` (`lesson_number`),
  ADD KEY `idx_subject_quarter` (`subject_id`,`quarter`),
  ADD KEY `idx_level_quarter` (`level`,`quarter`);

--
-- Indexes for table `lesson_scores`
--
ALTER TABLE `lesson_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `idx_student_lesson` (`student_id`,`lesson_id`),
  ADD KEY `idx_taken_at` (`taken_at`);

--
-- Indexes for table `quarter_exams`
--
ALTER TABLE `quarter_exams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_quarter_exam` (`subject_id`,`quarter`,`level`);

--
-- Indexes for table `quarter_exam_questions`
--
ALTER TABLE `quarter_exam_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exam_order` (`quarter_exam_id`,`order_index`);

--
-- Indexes for table `quarter_exam_scores`
--
ALTER TABLE `quarter_exam_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_quarter_exam` (`student_id`,`quarter_exam_id`),
  ADD KEY `quarter_exam_id` (`quarter_exam_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `idx_student_quarter` (`student_id`,`subject_id`,`quarter`);

--
-- Indexes for table `quarter_grades`
--
ALTER TABLE `quarter_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject_quarter` (`student_id`,`subject_id`,`quarter`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `idx_student_quarter` (`student_id`,`quarter`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lesson_quiz` (`lesson_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_order` (`quiz_id`,`order_index`);

--
-- Indexes for table `student_progress`
--
ALTER TABLE `student_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_lesson` (`student_id`,`lesson_id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `idx_student_status` (`student_id`,`status`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `teacher_levels`
--
ALTER TABLE `teacher_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_level` (`teacher_id`,`level`);

--
-- Indexes for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_subject` (`teacher_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_level` (`level`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `exam_requests`
--
ALTER TABLE `exam_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `final_average_requests`
--
ALTER TABLE `final_average_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `final_grades`
--
ALTER TABLE `final_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `lesson_scores`
--
ALTER TABLE `lesson_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `quarter_exams`
--
ALTER TABLE `quarter_exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quarter_exam_questions`
--
ALTER TABLE `quarter_exam_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quarter_exam_scores`
--
ALTER TABLE `quarter_exam_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quarter_grades`
--
ALTER TABLE `quarter_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `student_progress`
--
ALTER TABLE `student_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `teacher_levels`
--
ALTER TABLE `teacher_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `exam_requests`
--
ALTER TABLE `exam_requests`
  ADD CONSTRAINT `exam_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_requests_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `final_average_requests`
--
ALTER TABLE `final_average_requests`
  ADD CONSTRAINT `final_average_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `final_grades`
--
ALTER TABLE `final_grades`
  ADD CONSTRAINT `final_grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `final_grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lesson_scores`
--
ALTER TABLE `lesson_scores`
  ADD CONSTRAINT `lesson_scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_scores_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_scores_ibfk_3` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quarter_exams`
--
ALTER TABLE `quarter_exams`
  ADD CONSTRAINT `quarter_exams_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quarter_exam_questions`
--
ALTER TABLE `quarter_exam_questions`
  ADD CONSTRAINT `quarter_exam_questions_ibfk_1` FOREIGN KEY (`quarter_exam_id`) REFERENCES `quarter_exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quarter_exam_scores`
--
ALTER TABLE `quarter_exam_scores`
  ADD CONSTRAINT `quarter_exam_scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quarter_exam_scores_ibfk_2` FOREIGN KEY (`quarter_exam_id`) REFERENCES `quarter_exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quarter_exam_scores_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quarter_grades`
--
ALTER TABLE `quarter_grades`
  ADD CONSTRAINT `quarter_grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quarter_grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_progress`
--
ALTER TABLE `student_progress`
  ADD CONSTRAINT `student_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_levels`
--
ALTER TABLE `teacher_levels`
  ADD CONSTRAINT `teacher_levels_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD CONSTRAINT `teacher_subjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
