<?php
use App\Application\TeacherDashboardService;

require_once '../config/config.php';
requireRole(['teacher']);

$pageTitle = 'Teacher Dashboard';
include '../includes/header.php';

$service = new TeacherDashboardService();
$data = $service->getDashboardData(getCurrentUserId());

$teacher = $data['teacher'];
$assignedSubjects = $data['assignedSubjects'];
$totalStudents = $data['totalStudents'];
$pendingRequests = $data['pendingRequests'];
$totalLessons = $data['totalLessons'];
$recentRequests = $data['recentRequests'];
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Welcome, <?php echo htmlspecialchars($teacher['name']); ?>!</h1>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Assigned Subjects</div>
            <div class="stat-value"><?php echo count($assignedSubjects); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Students</div>
            <div class="stat-value"><?php echo $totalStudents; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending Exam Requests</div>
            <div class="stat-value"><?php echo $pendingRequests; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Lessons</div>
            <div class="stat-value"><?php echo $totalLessons; ?></div>
        </div>
    </div>
</div>

<?php if ($pendingRequests > 0): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Pending Exam Requests</h2>
            <a href="exam-requests.php" class="btn btn-primary">View All</a>
        </div>
        
        <?php if (empty($recentRequests)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No pending requests at the moment.
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Lesson</th>
                            <th>Subject</th>
                            <th>Request Type</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRequests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['lesson_number']); ?> - <?php echo htmlspecialchars($request['lesson_title']); ?></td>
                                <td><?php echo htmlspecialchars($request['subject_name']); ?></td>
                                <td><span class="badge badge-info"><?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?></span></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($request['requested_at'])); ?></td>
                                <td>
                                    <a href="exam-requests.php?action=approve&id=<?php echo $request['id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="exam-requests.php?action=deny&id=<?php echo $request['id']; ?>" class="btn btn-danger btn-sm">
                                        <i class="fas fa-times"></i> Deny
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Assigned Subjects</h2>
    </div>
    
    <div class="lesson-grid">
        <?php foreach ($assignedSubjects as $subject): ?>
            <div class="lesson-card unlocked">
                <div class="lesson-title"><?php echo htmlspecialchars($subject['name']); ?></div>
                <div class="lesson-description">
                    <strong>Code:</strong> <?php echo htmlspecialchars($subject['code']); ?>
                </div>
                <div class="lesson-footer">
                    <a href="lessons.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-book"></i> Manage Lessons
                    </a>
                    <a href="quizzes.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-question-circle"></i> Manage Quizzes
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
