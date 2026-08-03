<?php
require_once '../config/config.php';
requireRole(['admin']);

$pageTitle = 'Admin Dashboard';
include '../includes/header.php';

$conn = getDBConnection();

// Get statistics
$totalStudents = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'")->fetch_assoc()['total'];
$totalTeachers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'teacher'")->fetch_assoc()['total'];
$totalSubjects = $conn->query("SELECT COUNT(*) as total FROM subjects")->fetch_assoc()['total'];
$totalLessons = $conn->query("SELECT COUNT(*) as total FROM lessons")->fetch_assoc()['total'];
$totalQuizzes = $conn->query("SELECT COUNT(*) as total FROM quizzes")->fetch_assoc()['total'];
$pendingRequests = $conn->query("SELECT COUNT(*) as total FROM exam_requests WHERE status = 'pending'")->fetch_assoc()['total'];

// Get recent activities
$recentUsers = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Admin Dashboard</h1>
        <div>
            <a href="lessons.php" class="btn btn-primary">
                <i class="fas fa-book"></i> Manage Lessons
            </a>
            <a href="bulk-create-paces.php" class="btn btn-primary">
                <i class="fas fa-layer-group"></i> Bulk Create PACEs
            </a>
            <a href="create-tests.php" class="btn btn-primary">
                <i class="fas fa-clipboard-check"></i> Create Tests
            </a>
            <a href="unlock-first-paces.php" class="btn btn-success">
                <i class="fas fa-unlock"></i> Unlock First PACEs
            </a>
            <a href="unlock-all-paces.php" class="btn btn-warning">
                <i class="fas fa-unlock-alt"></i> Unlock All PACEs
            </a>
        </div>
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
            <div class="stat-label">Total Subjects</div>
            <div class="stat-value"><?php echo $totalSubjects; ?></div>
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
            <div class="stat-label">Pending Requests</div>
            <div class="stat-value"><?php echo $pendingRequests; ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Users</h2>
        <a href="users.php" class="btn btn-primary">Manage Users</a>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Level</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentUsers as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge badge-info"><?php echo ucfirst($user['role']); ?></span></td>
                        <td><?php echo $user['level']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
