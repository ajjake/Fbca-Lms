<?php
require_once '../config/config.php';
requireRole(['admin']);

$pageTitle = 'Reports & Analytics';
include '../includes/header.php';

$conn = getDBConnection();

// Get overall statistics
$totalStudents = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'")->fetch_assoc()['total'];
$totalTeachers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'teacher'")->fetch_assoc()['total'];
$totalLessons = $conn->query("SELECT COUNT(*) as total FROM lessons")->fetch_assoc()['total'];
$totalQuizzes = $conn->query("SELECT COUNT(*) as total FROM quizzes")->fetch_assoc()['total'];

// Get student progress statistics
$completedLessons = $conn->query("SELECT COUNT(*) as total FROM student_progress WHERE status = 'completed'")->fetch_assoc()['total'];
$unlockedLessons = $conn->query("SELECT COUNT(*) as total FROM student_progress WHERE status IN ('unlocked', 'in_progress', 'completed')")->fetch_assoc()['total'];

// Get exam request statistics
$pendingRequests = $conn->query("SELECT COUNT(*) as total FROM exam_requests WHERE status = 'pending'")->fetch_assoc()['total'];
$approvedRequests = $conn->query("SELECT COUNT(*) as total FROM exam_requests WHERE status = 'approved'")->fetch_assoc()['total'];
$deniedRequests = $conn->query("SELECT COUNT(*) as total FROM exam_requests WHERE status = 'denied'")->fetch_assoc()['total'];

// Get top performing students
$topStudents = $conn->query("
    SELECT u.name, u.username, AVG(fg.final_average) as avg_grade
    FROM users u
    INNER JOIN final_grades fg ON u.id = fg.student_id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY avg_grade DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Get subject statistics
$subjectStats = $conn->query("
    SELECT s.name, s.code,
           COUNT(DISTINCT l.id) as total_lessons,
           COUNT(DISTINCT q.id) as total_quizzes,
           COUNT(DISTINCT sp.student_id) as students_enrolled
    FROM subjects s
    LEFT JOIN lessons l ON s.id = l.subject_id
    LEFT JOIN quizzes q ON l.id = q.lesson_id
    LEFT JOIN student_progress sp ON l.id = sp.lesson_id
    GROUP BY s.id
    ORDER BY s.name
")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Reports & Analytics</h1>
        <a href="recalculate-grades.php" class="btn btn-primary" onclick="return confirm('This will recalculate all grades for all students. Continue?')">
            <i class="fas fa-calculator"></i> Recalculate All Grades
        </a>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Students</div>
            <div class="stat-value"><?php echo $totalStudents; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Teachers</div>
            <div class="stat-value"><?php echo $totalTeachers; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Lessons</div>
            <div class="stat-value"><?php echo $totalLessons; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Quizzes</div>
            <div class="stat-value"><?php echo $totalQuizzes; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Completed Lessons</div>
            <div class="stat-value"><?php echo $completedLessons; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Unlocked Lessons</div>
            <div class="stat-value"><?php echo $unlockedLessons; ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Exam Request Statistics</h2>
    </div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?php echo $pendingRequests; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Approved</div>
            <div class="stat-value"><?php echo $approvedRequests; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Denied</div>
            <div class="stat-value"><?php echo $deniedRequests; ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Top Performing Students</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Student Name</th>
                    <th>Username</th>
                    <th>Average Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topStudents as $index => $student): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                        <td><strong><?php echo number_format($student['avg_grade'], 2); ?>%</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Subject Statistics</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Code</th>
                    <th>Total Lessons</th>
                    <th>Total Quizzes</th>
                    <th>Students Enrolled</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjectStats as $stat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($stat['name']); ?></td>
                        <td><?php echo htmlspecialchars($stat['code']); ?></td>
                        <td><?php echo $stat['total_lessons']; ?></td>
                        <td><?php echo $stat['total_quizzes']; ?></td>
                        <td><?php echo $stat['students_enrolled']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
