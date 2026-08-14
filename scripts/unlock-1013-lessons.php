<?php
/**
 * Unlock all 1013 lessons for Level 1 students
 */

require_once 'config/config.php';
require_once 'config/database.php';

echo "FBCA LMS - Unlock 1013 Lessons for Level 1\n";
echo "============================================\n\n";

$conn = getDBConnection();

// Get all Level 1 students
echo "1. Finding Level 1 students...\n";
$students = $conn->query("SELECT id, name, username FROM users WHERE role = 'student' AND level = 1")->fetch_all(MYSQLI_ASSOC);
$studentCount = count($students);
echo "   Found $studentCount Level 1 students\n\n";

// Get all 1013 lessons
echo "2. Finding all 1013 lessons...\n";
$lessons = $conn->query("SELECT id, lesson_number, title FROM lessons WHERE lesson_number LIKE '%1013%'")->fetch_all(MYSQLI_ASSOC);
$lessonCount = count($lessons);
echo "   Found $lessonCount lessons with 1013\n\n";

if ($studentCount == 0) {
    echo "⚠ No Level 1 students found. Please create students first.\n";
    closeDBConnection($conn);
    exit(1);
}

if ($lessonCount == 0) {
    echo "⚠ No 1013 lessons found. Please create lessons first.\n";
    closeDBConnection($conn);
    exit(1);
}

// Unlock lessons for all Level 1 students
echo "3. Unlocking lessons for Level 1 students...\n";
$unlocked = 0;
$alreadyUnlocked = 0;
$errors = 0;

foreach ($students as $student) {
    foreach ($lessons as $lesson) {
        // Check if already unlocked
        $checkStmt = $conn->prepare("SELECT id, status FROM student_progress WHERE student_id = ? AND lesson_id = ?");
        $checkStmt->bind_param("ii", $student['id'], $lesson['id']);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        
        if ($existing) {
            if ($existing['status'] === 'locked') {
                // Update to unlocked
                $updateStmt = $conn->prepare("
                    UPDATE student_progress 
                    SET status = 'unlocked', unlocked_at = NOW() 
                    WHERE student_id = ? AND lesson_id = ?
                ");
                $updateStmt->bind_param("ii", $student['id'], $lesson['id']);
                if ($updateStmt->execute()) {
                    $unlocked++;
                } else {
                    $errors++;
                }
                $updateStmt->close();
            } else {
                $alreadyUnlocked++;
            }
        } else {
            // Insert new unlocked status
            $insertStmt = $conn->prepare("
                INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                VALUES (?, ?, 'unlocked', NOW())
            ");
            $insertStmt->bind_param("ii", $student['id'], $lesson['id']);
            if ($insertStmt->execute()) {
                $unlocked++;
            } else {
                $errors++;
            }
            $insertStmt->close();
        }
    }
}

closeDBConnection($conn);

echo "\n========================================\n";
echo "✓ Unlock process completed!\n";
echo "========================================\n\n";
echo "Summary:\n";
echo "  Students processed: $studentCount\n";
echo "  Lessons processed: $lessonCount\n";
echo "  Newly unlocked: $unlocked\n";
echo "  Already unlocked: $alreadyUnlocked\n";
if ($errors > 0) {
    echo "  Errors: $errors\n";
}
echo "\nAll 1013 lessons are now unlocked for Level 1 students!\n";
?>
