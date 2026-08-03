# FBCA Learning Management System (LMS)

A comprehensive web-based Learning Management System built with PHP, MySQL, HTML, CSS, and JavaScript. The system supports three user roles: Students, Teachers, and Administrators.

## Features

### Student Features
- **Dashboard**: View profile, current quarter, subjects, lesson progress, and grades
- **Lessons**: Browse and study lessons organized by subject and quarter
- **Lesson Locking**: Lessons unlock progressively based on quiz completion and teacher approval
- **Quizzes**: Take quizzes with multiple choice and true/false questions
- **Exam Requests**: Request approval to take lesson exams
- **Grades**: View quarter grades and final averages for all subjects
- **Progress Tracking**: Visual progress indicators for completed and unlocked lessons

### Teacher Features
- **Dashboard**: View assigned students, progress, and pending exam requests
- **Lesson Management**: Add, edit, and manage lessons with video content and materials
- **Quiz Management**: Create quizzes with questions, set passing scores, and time limits
- **Exam Approval**: Approve or deny student exam requests with optional remarks
- **Subject Assignment**: Manage lessons for assigned subjects

### Admin Features
- **User Management**: Create, edit, and delete users (students, teachers, admins)
- **Subject Management**: Add, edit, and manage subjects
- **Teacher Assignment**: Assign teachers to subjects
- **Reports & Analytics**: View system statistics, top performing students, and subject analytics
- **System Control**: Full access to all system features

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB equivalent)
- Apache/Nginx web server
- Modern web browser

## Installation

1. **Clone or download the project** to your web server directory (e.g., `htdocs` or `www`)

2. **Create the database**:
   ```sql
   CREATE DATABASE fbcals_db;
   ```

3. **Import the database schema**:
   - Open phpMyAdmin or MySQL command line
   - Select the `fbcals_db` database
   - Import `database/schema.sql`

4. **Configure database connection**:
   - Edit `config/database.php`
   - Update database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'fbcals_db');
     ```

5. **Set up file upload directories**:
   - The system will automatically create `uploads/videos/` and `uploads/materials/` directories
   - Ensure these directories have write permissions (chmod 755 or 777)

6. **Configure base URL**:
   - Edit `config/config.php`
   - Update `BASE_URL` to match your installation path:
     ```php
     define('BASE_URL', 'http://localhost/FBCA%20Web%20System/');
     ```

7. **Access the system**:
   - Navigate to `http://localhost/FBCA%20Web%20System/` in your browser
   - Default admin credentials:
     - Username: `admin`
     - Password: `admin123`

## Database Schema

The system includes the following main tables:

- **users**: User accounts (students, teachers, admins)
- **subjects**: Subject catalog
- **lessons**: Lesson content with video and materials
- **quizzes**: Quiz definitions
- **quiz_questions**: Quiz questions and answers
- **student_progress**: Lesson unlock status and progress
- **exam_requests**: Student exam approval requests
- **lesson_scores**: Quiz attempt scores
- **quarter_exams**: Quarter exam definitions
- **quarter_grades**: Calculated quarter grades
- **final_grades**: Final subject averages

## Lesson Structure

- **4 Quarters** per academic year
- **3 Lessons** per quarter per subject
- **12 Total Lessons** per subject
- **7 Subjects**: English, World Building, Science, Filipino, Araling Panlipunan, Mathematics, Computer

## Lesson Locking Logic

1. **Lesson 1** is unlocked by default for all students
2. **Lesson 2** unlocks when:
   - Lesson 1 quiz is passed (≥ passing score)
   - Teacher/Admin approves exam access
3. **Lesson 3** unlocks when Lesson 2 is passed
4. This pattern continues for all subsequent lessons

## Grading System

- **Lesson Quiz Score**: Percentage score from quiz attempt
- **Quarter Grade**: Average of lesson quiz scores + quarter exam score
- **Final Grade**: Average of all 4 quarter grades

## File Structure

```
FBCA Web System/
├── admin/              # Admin panel pages
├── api/                # API endpoints
├── assets/
│   ├── css/           # Stylesheets
│   └── js/            # JavaScript files
├── config/            # Configuration files
├── database/          # Database schema
├── includes/          # Shared PHP includes
├── student/           # Student pages
├── teacher/           # Teacher panel pages
├── uploads/           # Uploaded files (videos, materials)
├── index.php          # Main entry point
├── login.php          # Login page
└── README.md          # This file
```

## Security Features

- Session-based authentication
- Password hashing (bcrypt)
- Role-based access control
- SQL injection prevention (prepared statements)
- XSS protection (htmlspecialchars)

## Usage Guide

### For Students:
1. Login with student credentials
2. View dashboard to see available lessons
3. Study unlocked lessons
4. Request exam approval for lesson quizzes
5. Take quizzes after approval
6. View grades and progress

### For Teachers:
1. Login with teacher credentials
2. View assigned subjects
3. Add/edit lessons and quizzes
4. Review and approve/deny exam requests
5. Monitor student progress

### For Admins:
1. Login with admin credentials
2. Manage users, subjects, and system settings
3. Assign teachers to subjects
4. View system reports and analytics
5. Override grades or unlock lessons if needed

## Default Subjects

The system comes pre-configured with 7 subjects:
1. English
2. World Building
3. Science
4. Filipino
5. Araling Panlipunan
6. Mathematics
7. Computer

## Notes

- Video uploads support MP4 files or YouTube URLs
- Material files support PDF format
- Quiz time limits are optional (0 = no limit)
- Passing scores are configurable per quiz (default: 75%)
- The system automatically calculates quarter and final grades

## Support

For issues or questions, please refer to the code documentation or contact the system administrator.

## License

This project is developed for FBCA (Full Bright Christian Academy) educational purposes.
