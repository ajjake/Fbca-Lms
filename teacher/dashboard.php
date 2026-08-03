<?php
require_once '../config/config.php';
requireRole(['teacher']);

$pageTitle = 'Teacher Dashboard';
include '../includes/header.php';

$conn = getDBConnection();
$teacherId = getCurrentUserId();

// Get teacher info
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get assigned subjects
$stmt = $conn->prepare("
    SELECT s.* FROM subjects s
    INNER JOIN teacher_subjects ts ON s.id = ts.subject_id
    WHERE ts.teacher_id = ?
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$assignedSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total students (restricted to teacher's assigned levels if any)
$stmt = $conn->prepare("SELECT level FROM teacher_levels WHERE teacher_id = ?");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$tl = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$assignedLv = array_column($tl, 'level');

if (!empty($assignedLv)) {
    // build placeholders
    $placeholders = implode(',', array_fill(0, count($assignedLv), '?'));
    $types = str_repeat('i', count($assignedLv));
    $sql = "SELECT COUNT(*) as total FROM users WHERE role = 'student' AND level IN ($placeholders)";
    $pstmt = $conn->prepare($sql);
    // bind params dynamically
    $bind_names[] = $types;
    for ($i = 0; $i < count($assignedLv); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $assignedLv[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array(array($pstmt, 'bind_param'), $bind_names);
    $pstmt->execute();
    $totalStudents = $pstmt->get_result()->fetch_assoc()['total'];
    $pstmt->close();
} else {
    $totalStudents = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'")->fetch_assoc()['total'];
}

// Get pending exam requests
$stmt = $conn->prepare("
    SELECT COUNT(*) as total FROM exam_requests er
    INNER JOIN lessons l ON er.lesson_id = l.id
    INNER JOIN teacher_subjects ts ON l.subject_id = ts.subject_id
    WHERE ts.teacher_id = ? AND er.status = 'pending'
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$pendingRequests = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get total lessons managed
$stmt = $conn->prepare("
    SELECT COUNT(*) as total FROM lessons l
    INNER JOIN teacher_subjects ts ON l.subject_id = ts.subject_id
    WHERE ts.teacher_id = ?
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$totalLessons = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get recent exam requests
$stmt = $conn->prepare("
    SELECT er.*, u.name as student_name, l.title as lesson_title, l.lesson_number, s.name as subject_name
    FROM exam_requests er
    INNER JOIN users u ON er.student_id = u.id
    INNER JOIN lessons l ON er.lesson_id = l.id
    INNER JOIN subjects s ON l.subject_id = s.id
    INNER JOIN teacher_subjects ts ON l.subject_id = ts.subject_id
    WHERE ts.teacher_id = ? AND er.status = 'pending'
    ORDER BY er.requested_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$recentRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);
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
