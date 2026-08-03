<?php
/**
 * Unlock first PACEs for all students based on their level
 * Level 1: 1013 (Q1), 1016 (Q2), 1019 (Q3), 1022 (Q4)
 * Level 2: 1025 (Q1), 1028 (Q2), 1031 (Q3), 1034 (Q4)
 * Pattern: base = 1013 + (level - 1) * 12 + (quarter - 1) * 3
 */

require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();

// Get all students
$students = $conn->query("SELECT id, level FROM users WHERE role = 'student'")->fetch_all(MYSQLI_ASSOC);

$unlocked = 0;
$skipped = 0;
$errors = [];

foreach ($students as $student) {
    $studentId = $student['id'];
    $level = $student['level'];
    
    // Get all subjects
    $subjects = $conn->query("SELECT id, code FROM subjects")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($subjects as $subject) {
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            // Calculate first PACE number for this level and quarter
            // Level 1 Q1 = 1013, Level 1 Q2 = 1016, Level 1 Q3 = 1019, Level 1 Q4 = 1022
            // Level 2 Q1 = 1025, Level 2 Q2 = 1028, Level 2 Q3 = 1031, Level 2 Q4 = 1034
            $basePace = 1013 + ($level - 1) * 12 + ($quarter - 1) * 3;
            $paceNumberWithCode = $subject['code'] . ' ' . $basePace;
            $paceNumberOnly = (string)$basePace;
            
            // Find lesson with this PACE number - handle both formats:
            // 1. "SUBJECT_CODE 1013" (e.g., "WB 1013")
            // 2. Just "1013" (without subject code prefix)
            $stmt = $conn->prepare("
                SELECT id FROM lessons 
                WHERE subject_id = ? AND quarter = ? AND level = ?
                AND (
                    pace_number = ? OR 
                    pace_number = ? OR
                    lesson_number = ? OR
                    lesson_number = ?
                )
                AND pace_type = 'lesson'
                ORDER BY order_index ASC
                LIMIT 1
            ");
            $stmt->bind_param("iiissss", $subject['id'], $quarter, $level, 
                $paceNumberWithCode, $paceNumberOnly, $paceNumberWithCode, $paceNumberOnly);
            $stmt->execute();
            $firstPace = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($firstPace) {
                // Check if already unlocked
                $checkStmt = $conn->prepare("SELECT id FROM student_progress WHERE student_id = ? AND lesson_id = ?");
                $checkStmt->bind_param("ii", $studentId, $firstPace['id']);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows == 0) {
                    // Unlock first PACE
                    $insertStmt = $conn->prepare("
                        INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                        VALUES (?, ?, 'unlocked', NOW())
                    ");
                    $insertStmt->bind_param("ii", $studentId, $firstPace['id']);
                    if ($insertStmt->execute()) {
                        $unlocked++;
                    } else {
                        $errors[] = "Failed to unlock PACE $basePace for student ID $studentId: " . $insertStmt->error;
                    }
                    $insertStmt->close();
                } else {
                    $skipped++;
                }
                $checkStmt->close();
            } else {
                $errors[] = "PACE $basePace not found for Subject {$subject['code']}, Level $level, Quarter $quarter";
            }
        }
    }
}

closeDBConnection($conn);

$pageTitle = 'Unlock First PACEs';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Unlock First PACEs - Results</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <strong>Completed!</strong><br>
        Unlocked: <strong><?php echo $unlocked; ?></strong> first PACE(s)<br>
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
        <h3>PACE Unlock Pattern</h3>
        <ul>
            <li><strong>Level 1:</strong> 1013 (Q1), 1016 (Q2), 1019 (Q3), 1022 (Q4)</li>
            <li><strong>Level 2:</strong> 1025 (Q1), 1028 (Q2), 1031 (Q3), 1034 (Q4)</li>
            <li><strong>Level 3:</strong> 1037 (Q1), 1040 (Q2), 1043 (Q3), 1046 (Q4)</li>
            <li>And so on...</li>
        </ul>
        <p><strong>Note:</strong> This unlocks the first PACE of each quarter for all subjects based on each student's level.</p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
