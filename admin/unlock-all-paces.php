<?php
/**
 * Unlock ALL PACEs/Lessons for ALL students
 * This is useful for testing purposes
 */

require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();

// Get all students
$students = $conn->query("SELECT id, level FROM users WHERE role = 'student'")->fetch_all(MYSQLI_ASSOC);

$unlocked = 0;
$skipped = 0;
$errors = [];

// Get ALL lessons (regardless of level - unlock everything for testing)
$allLessons = $conn->query("SELECT id FROM lessons ORDER BY subject_id, quarter, order_index")->fetch_all(MYSQLI_ASSOC);

foreach ($students as $student) {
    $studentId = $student['id'];
    
    foreach ($allLessons as $lesson) {
        $lessonId = $lesson['id'];
        
        // Check if already unlocked
        $checkStmt = $conn->prepare("SELECT id, status FROM student_progress WHERE student_id = ? AND lesson_id = ?");
        $checkStmt->bind_param("ii", $studentId, $lessonId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        
        if ($existing) {
            // Update if locked, skip if already unlocked
            if ($existing['status'] === 'locked') {
                $updateStmt = $conn->prepare("
                    UPDATE student_progress 
                    SET status = 'unlocked', unlocked_at = NOW() 
                    WHERE student_id = ? AND lesson_id = ?
                ");
                $updateStmt->bind_param("ii", $studentId, $lessonId);
                if ($updateStmt->execute()) {
                    $unlocked++;
                } else {
                    $errors[] = "Failed to unlock lesson ID $lessonId for student ID $studentId: " . $updateStmt->error;
                }
                $updateStmt->close();
            } else {
                $skipped++;
            }
        } else {
            // Insert new unlocked status
            $insertStmt = $conn->prepare("
                INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                VALUES (?, ?, 'unlocked', NOW())
            ");
            $insertStmt->bind_param("ii", $studentId, $lessonId);
            
            if ($insertStmt->execute()) {
                $unlocked++;
            } else {
                $errors[] = "Failed to unlock lesson ID $lessonId for student ID $studentId: " . $insertStmt->error;
            }
            $insertStmt->close();
        }
    }
}

closeDBConnection($conn);

$pageTitle = 'Unlock All PACEs';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Unlock All PACEs - Results</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <strong>Completed!</strong><br>
        Unlocked: <strong><?php echo $unlocked; ?></strong> PACE(s)/Lesson(s)<br>
        Skipped: <strong><?php echo $skipped; ?></strong> already unlocked
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Warnings:</strong>
            <ul style="margin-top: 0.5rem;">
                <?php foreach (array_slice($errors, 0, 20) as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
                <?php if (count($errors) > 20): ?>
                    <li><em>... and <?php echo count($errors) - 20; ?> more warnings</em></li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; margin-top: 1rem;">
        <h3>What was unlocked?</h3>
        <p><strong>All PACEs and lessons for ALL students have been unlocked.</strong></p>
        <p>This includes:</p>
        <ul>
            <li>All regular PACEs (lessons)</li>
            <li>All Monthly Tests</li>
            <li>All Quarter Tests</li>
            <li>Lessons from all levels (students can access any level's content)</li>
        </ul>
        <p><strong>Note:</strong> This unlocks ALL lessons regardless of level or prerequisites. Use this for testing purposes only.</p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
