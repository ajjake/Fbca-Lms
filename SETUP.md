# FBCA LMS Setup Guide

## Quick Start

### 1. Database Setup

1. Create a MySQL database:
   ```sql
   CREATE DATABASE fbcals_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Import the schema:
   - Open phpMyAdmin or use MySQL command line
   - Select `fbcals_db` database
   - Import `database/schema.sql`

### 2. Configuration

1. Edit `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'fbcals_db');
   ```

2. Edit `config/config.php`:
   ```php
   define('BASE_URL', 'http://localhost/FBCA%20Web%20System/');
   ```
   Update this to match your installation path.

### 3. File Permissions

Ensure these directories are writable:
- `uploads/videos/` (for video file uploads)
- `uploads/materials/` (for PDF materials)

On Linux/Mac:
```bash
chmod 755 uploads/videos
chmod 755 uploads/materials
```

### 4. Initial Login

- **URL**: `http://localhost/FBCA%20Web%20System/login.php`
- **Default Admin**:
  - Username: `admin`
  - Password: `admin123`

**Important**: Change the admin password immediately after first login!

### 5. Initialize Student Progress (Optional)

After creating students, run the initialization script to unlock first lessons:

1. Login as admin
2. Navigate to: `includes/init-student-progress.php` (or run via command line)
3. This will unlock the first lesson of each subject for all students

Alternatively, the system will automatically unlock lessons when:
- A student is created and assigned to a level
- A teacher approves an exam request

## Creating Your First Content

### As Admin:

1. **Create Users**:
   - Go to Admin → Users
   - Add students and teachers
   - Assign teachers to subjects (Admin → Users → Assign Subjects)

2. **Verify Subjects**:
   - Go to Admin → Subjects
   - 7 default subjects are already created
   - You can add more if needed

### As Teacher:

1. **Add Lessons**:
   - Go to Teacher → Lessons
   - Select a subject
   - Click "Add New Lesson"
   - Fill in lesson details
   - Upload video (MP4) or provide YouTube URL
   - Upload materials (PDF) if available

2. **Create Quizzes**:
   - Go to Teacher → Quizzes
   - Select subject and lesson
   - Click "Add Quiz"
   - Set passing score and time limit
   - Add questions (multiple choice or true/false)

3. **Approve Exams**:
   - Go to Teacher → Exam Requests
   - Review pending requests
   - Approve or deny with optional remarks

### As Student:

1. **Study Lessons**:
   - View dashboard for available lessons
   - Click on unlocked lessons to study
   - Watch videos and download materials

2. **Request Exam Approval**:
   - After studying a lesson, click "Request Exam Approval"
   - Wait for teacher approval

3. **Take Quizzes**:
   - After approval, take the quiz
   - View results and unlock next lesson

## Lesson Structure

- **4 Quarters** per academic year
- **3 Lessons** per quarter per subject
- **12 Total Lessons** per subject
- **7 Subjects** total

Lesson naming format: `Subject Code + Number` (e.g., "English 1001", "English 1002")

## Grading System

- **Lesson Quiz**: Percentage score from quiz attempt
- **Quarter Grade**: (Lesson Average + Quarter Exam) / 2
- **Final Grade**: Average of all 4 quarter grades

## Troubleshooting

### Database Connection Error
- Check database credentials in `config/database.php`
- Verify database exists and is accessible
- Check MySQL service is running

### File Upload Issues
- Check directory permissions (755 or 777)
- Verify `uploads/` directory exists
- Check PHP `upload_max_filesize` and `post_max_size` settings

### Session Issues
- Ensure PHP sessions are enabled
- Check session directory is writable
- Clear browser cookies if login fails

### Lesson Not Unlocking
- Verify student passed previous lesson quiz
- Check exam request was approved
- Ensure lesson order_index is correct

## Security Recommendations

1. **Change Default Password**: Immediately change admin password
2. **Use HTTPS**: Enable SSL in production
3. **File Upload Security**: Restrict file types and sizes
4. **Database Security**: Use strong database passwords
5. **Session Security**: Configure secure session settings
6. **Regular Backups**: Backup database regularly

## Support

For technical issues, check:
- PHP error logs
- MySQL error logs
- Browser console for JavaScript errors

## Next Steps

1. Create user accounts for students and teachers
2. Assign teachers to subjects
3. Add lessons and quizzes
4. Test the student workflow
5. Customize styling if needed
