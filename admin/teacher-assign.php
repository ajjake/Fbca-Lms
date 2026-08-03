<?php
require_once '../config/config.php';
requireRole(['admin']);

$teacherId = $_GET['teacher_id'] ?? 0;
if (!$teacherId) {
    header('Location: users.php');
    exit();
}

$conn = getDBConnection();

// Get teacher info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    header('Location: users.php');
    exit();
}

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjectIds = $_POST['subjects'] ?? [];
    $levels = $_POST['levels'] ?? [];
    
    // Remove all current assignments
    $stmt = $conn->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?");
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $stmt->close();
    
    // Add new assignments
    foreach ($subjectIds as $subjectId) {
        $stmt = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $teacherId, $subjectId);
        $stmt->execute();
        $stmt->close();
    }
    // Handle level assignments (create table if missing)
    $conn->query("CREATE TABLE IF NOT EXISTS teacher_levels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        level INT NOT NULL,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_teacher_level (teacher_id, level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Remove current level assignments
    $stmt = $conn->prepare("DELETE FROM teacher_levels WHERE teacher_id = ?");
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $stmt->close();

    foreach ($levels as $lv) {
        $lvl = (int)$lv;
        $stmt = $conn->prepare("INSERT INTO teacher_levels (teacher_id, level) VALUES (?, ?)");
        $stmt->bind_param("ii", $teacherId, $lvl);
        $stmt->execute();
        $stmt->close();
    }
    
    $message = 'Subject assignments updated successfully.';
}

// Get all subjects
$allSubjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get assigned subjects
$stmt = $conn->prepare("SELECT subject_id FROM teacher_subjects WHERE teacher_id = ?");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$assigned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$assignedIds = array_column($assigned, 'subject_id');
$stmt->close();

// Ensure teacher_levels table exists and get assigned levels
$conn->query("CREATE TABLE IF NOT EXISTS teacher_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    level INT NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_level (teacher_id, level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$stmt = $conn->prepare("SELECT level FROM teacher_levels WHERE teacher_id = ?");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$assignedLevels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$assignedLevelIds = array_column($assignedLevels, 'level');
$stmt->close();

// Get available levels from students
$levelsList = $conn->query("SELECT DISTINCT level FROM users WHERE role = 'student' ORDER BY level")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);

$pageTitle = 'Assign Subjects to Teacher';
include '../includes/header.php';

$message = $_GET['message'] ?? '';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Assign Subjects to Teacher</h1>
        <a href="users.php" class="btn btn-secondary">Back to Users</a>
    </div>
    
    <div style="margin-bottom: 1.5rem;">
        <p><strong>Teacher:</strong> <?php echo htmlspecialchars($teacher['name']); ?> (<?php echo htmlspecialchars($teacher['username']); ?>)</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Select Subjects</label>
            <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 1rem; border-radius: 5px;">
                <?php foreach ($allSubjects as $subject): ?>
                    <div style="margin-bottom: 0.5rem;">
                        <label>
                            <input type="checkbox" name="subjects[]" value="<?php echo $subject['id']; ?>" 
                                   <?php echo in_array($subject['id'], $assignedIds) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($subject['name']); ?> (<?php echo htmlspecialchars($subject['code']); ?>)
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group" style="margin-top:1rem;">
            <label class="form-label">Assign Levels (students)</label>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 1rem; border-radius: 5px;">
                <?php foreach ($levelsList as $lv): $lvl = (int)$lv['level']; ?>
                    <div style="margin-bottom: 0.5rem;">
                        <label>
                            <input type="checkbox" name="levels[]" value="<?php echo $lvl; ?>" <?php echo in_array($lvl, $assignedLevelIds) ? 'checked' : ''; ?>>
                            Level <?php echo $lvl; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Assignments
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
