<?php
use App\Application\AdminReportService;

require_once '../config/config.php';
requireRole(['admin']);

$pageTitle = 'Reports & Analytics';
include '../includes/header.php';

$service = new AdminReportService();
$data = $service->getReportData();

$totalStudents = $data['totalStudents'];
$totalTeachers = $data['totalTeachers'];
$totalLessons = $data['totalLessons'];
$totalQuizzes = $data['totalQuizzes'];
$completedLessons = $data['completedLessons'];
$unlockedLessons = $data['unlockedLessons'];
$pendingRequests = $data['pendingRequests'];
$approvedRequests = $data['approvedRequests'];
$deniedRequests = $data['deniedRequests'];
$topStudents = $data['topStudents'];
$subjectStats = $data['subjectStats'];
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
