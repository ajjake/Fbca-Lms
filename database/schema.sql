-- FBCA Learning Management System Database Schema
-- Created for complete LMS with Student, Teacher, and Admin roles

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    level INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subjects table
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Teacher-Subject assignments
CREATE TABLE IF NOT EXISTS teacher_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_subject (teacher_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lessons table
CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    lesson_number VARCHAR(20) NOT NULL, -- e.g., "English 1001"
    title VARCHAR(200) NOT NULL,
    description TEXT,
    quarter INT NOT NULL CHECK (quarter BETWEEN 1 AND 4),
    level INT NOT NULL,
    video_url TEXT,
    video_file VARCHAR(255), -- For uploaded MP4 files
    material_file VARCHAR(255), -- PDF or other materials
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_subject_quarter (subject_id, quarter),
    INDEX idx_level_quarter (level, quarter),
    UNIQUE KEY unique_lesson_number (lesson_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quizzes table
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    passing_score DECIMAL(5,2) DEFAULT 75.00,
    time_limit INT DEFAULT 0, -- in minutes, 0 = no limit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_lesson_quiz (lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz questions table
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false') NOT NULL,
    option_a VARCHAR(255),
    option_b VARCHAR(255),
    option_c VARCHAR(255),
    option_d VARCHAR(255),
    correct_answer VARCHAR(10) NOT NULL, -- 'A', 'B', 'C', 'D', or 'True', 'False'
    points DECIMAL(5,2) DEFAULT 1.00,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_quiz_order (quiz_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Student progress table
CREATE TABLE IF NOT EXISTS student_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lesson_id INT NOT NULL,
    status ENUM('locked', 'unlocked', 'in_progress', 'completed') DEFAULT 'locked',
    unlocked_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_lesson (student_id, lesson_id),
    INDEX idx_student_status (student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exam requests table
CREATE TABLE IF NOT EXISTS exam_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lesson_id INT NOT NULL,
    request_type ENUM('lesson_exam', 'quarter_exam') NOT NULL,
    quarter INT NULL, -- For quarter exams
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    remarks TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lesson scores table
CREATE TABLE IF NOT EXISTS lesson_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    quiz_id INT NOT NULL,
    lesson_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    total_points DECIMAL(5,2) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    passed BOOLEAN DEFAULT FALSE,
    time_taken INT DEFAULT 0, -- in seconds
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    INDEX idx_student_lesson (student_id, lesson_id),
    INDEX idx_taken_at (taken_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quarter exams table
CREATE TABLE IF NOT EXISTS quarter_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    quarter INT NOT NULL CHECK (quarter BETWEEN 1 AND 4),
    level INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    passing_score DECIMAL(5,2) DEFAULT 75.00,
    time_limit INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_quarter_exam (subject_id, quarter, level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quarter exam questions table
CREATE TABLE IF NOT EXISTS quarter_exam_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quarter_exam_id INT NOT NULL,
    question TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false') NOT NULL,
    option_a VARCHAR(255),
    option_b VARCHAR(255),
    option_c VARCHAR(255),
    option_d VARCHAR(255),
    correct_answer VARCHAR(10) NOT NULL,
    points DECIMAL(5,2) DEFAULT 1.00,
    order_index INT DEFAULT 0,
    FOREIGN KEY (quarter_exam_id) REFERENCES quarter_exams(id) ON DELETE CASCADE,
    INDEX idx_exam_order (quarter_exam_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quarter exam scores table
CREATE TABLE IF NOT EXISTS quarter_exam_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    quarter_exam_id INT NOT NULL,
    subject_id INT NOT NULL,
    quarter INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    total_points DECIMAL(5,2) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    passed BOOLEAN DEFAULT FALSE,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quarter_exam_id) REFERENCES quarter_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_quarter_exam (student_id, quarter_exam_id),
    INDEX idx_student_quarter (student_id, subject_id, quarter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quarter grades table
CREATE TABLE IF NOT EXISTS quarter_grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    quarter INT NOT NULL CHECK (quarter BETWEEN 1 AND 4),
    lesson_average DECIMAL(5,2) DEFAULT 0.00,
    quarter_exam_score DECIMAL(5,2) DEFAULT 0.00,
    final_grade DECIMAL(5,2) DEFAULT 0.00,
    computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject_quarter (student_id, subject_id, quarter),
    INDEX idx_student_quarter (student_id, quarter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Final grades table
CREATE TABLE IF NOT EXISTS final_grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    q1_grade DECIMAL(5,2) DEFAULT 0.00,
    q2_grade DECIMAL(5,2) DEFAULT 0.00,
    q3_grade DECIMAL(5,2) DEFAULT 0.00,
    q4_grade DECIMAL(5,2) DEFAULT 0.00,
    final_average DECIMAL(5,2) DEFAULT 0.00,
    computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject (student_id, subject_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default subjects
INSERT INTO subjects (name, code) VALUES
('English', 'ENG'),
('World Building', 'WB'),
('Science', 'SCI'),
('Filipino', 'FIL'),
('Araling Panlipunan', 'AP'),
('Mathematics', 'MATH'),
('Computer', 'COMP');

-- Insert default admin user (password: admin123 - should be hashed in production)
-- Default password hash for 'admin123' using password_hash()
INSERT INTO users (username, password, name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@fbcals.com', 'admin');
