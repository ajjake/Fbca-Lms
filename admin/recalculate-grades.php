<?php
/**
 * Recalculate All Grades
 * This script recalculates all quarter and final grades for all students
 * Useful for fixing any grade calculation issues
 */

require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();

// Get all students
$students = $conn->query("SELECT id FROM users WHERE role = 'student'")->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query("SELECT id FROM subjects")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/grading.php';

$processed = 0;
$errors = 0;

foreach ($students as $student) {
    foreach ($subjects as $subject) {
        // Get all lessons for this subject
        $lessons = $conn->query("SELECT id FROM lessons WHERE subject_id = {$subject['id']}")->fetch_all(MYSQLI_ASSOC);
        
        foreach ($lessons as $lesson) {
            // Check if student has any scores for this lesson
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lesson_scores WHERE student_id = ? AND lesson_id = ?");
            $stmt->bind_param("ii", $student['id'], $lesson['id']);
            $stmt->execute();
            $hasScores = $stmt->get_result()->fetch_assoc()['count'] > 0;
            $stmt->close();
            
            if ($hasScores) {
                try {
                    updateQuarterGrades($conn, $student['id'], $lesson['id']);
                    $processed++;
                } catch (Exception $e) {
                    $errors++;
                }
            }
        }
    }
}

closeDBConnection($conn);

$pageTitle = 'Recalculate Grades';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Recalculate Grades</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <strong>Grade recalculation completed!</strong><br>
        Processed: <?php echo $processed; ?> grade updates<br>
        <?php if ($errors > 0): ?>
            Errors: <?php echo $errors; ?><br>
        <?php endif; ?>
    </div>
    
    <p>All quarter grades and final averages have been recalculated for all students.</p>
    <p><strong>Grade Calculation Formula:</strong></p>
    <ul>
        <li><strong>Lesson Average:</strong> Average of all quiz scores for a subject in a quarter</li>
        <li><strong>Quarter Grade:</strong> (Lesson Average + Quarter Exam) / 2 (or just Lesson Average if no exam)</li>
        <li><strong>Final Average:</strong> Average of all 4 quarter grades per subject</li>
    </ul>
</div>

<?php include '../includes/footer.php'; ?>
