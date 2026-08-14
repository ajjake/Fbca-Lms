<?php
/**
 * Unlock ALL Lessons for ALL Students (Testing Mode)
 * This script unlocks every lesson for every student regardless of level
 */

require_once 'config/config.php';
require_once 'config/database.php';

echo "FBCA LMS - Unlock ALL Lessons for Testing (All Levels)\n";
echo "======================================================\n\n";

$conn = getDBConnection();

// Get all students
echo "1. Finding all students...\n";
$students = $conn->query("SELECT id, name, username, level FROM users WHERE role = 'student'")->fetch_all(MYSQLI_ASSOC);
$studentCount = count($students);
echo "   Found $studentCount students\n\n";

if ($studentCount == 0) {
    echo "⚠ No students found. Please create students first.\n";
    closeDBConnection($conn);
    exit(1);
}

// Get ALL lessons (regardless of level)
echo "2. Finding all lessons...\n";
$lessons = $conn->query("SELECT id, lesson_number, title, level FROM lessons ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$lessonCount = count($lessons);
echo "   Found $lessonCount lessons\n\n";

if ($lessonCount == 0) {
    echo "⚠ No lessons found. Please create lessons first.\n";
    closeDBConnection($conn);
    exit(1);
}

// Unlock ALL lessons for ALL students (testing mode - ignore level restrictions)
echo "3. Unlocking ALL lessons for ALL students (testing mode)...\n";
echo "   ⚠ This will unlock lessons regardless of student/lesson level matching.\n\n";

$unlocked = 0;
$alreadyUnlocked = 0;
$errors = 0;

foreach ($students as $student) {
    echo "   Processing: {$student['name']} (Level {$student['level']})...\n";
    
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
echo "  Total combinations: " . ($studentCount * $lessonCount) . "\n";
echo "  Newly unlocked: $unlocked\n";
echo "  Already unlocked: $alreadyUnlocked\n";
if ($errors > 0) {
    echo "  Errors: $errors\n";
}
echo "\n";
echo "✓ ALL lessons are now unlocked for ALL students!\n";
echo "  (Testing mode - level restrictions bypassed)\n";
?>
