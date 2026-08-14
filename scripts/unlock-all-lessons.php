<?php
/**
 * Unlock All Lessons for Testing
 * This script unlocks all lessons for all students (or specific level)
 */

require_once 'config/config.php';
require_once 'config/database.php';

echo "FBCA LMS - Unlock All Lessons for Testing\n";
echo "==========================================\n\n";

$conn = getDBConnection();

// Get level filter (optional - set to 0 for all students)
$levelFilter = isset($argv[1]) ? (int)$argv[1] : 0;

// Get all students
if ($levelFilter > 0) {
    echo "1. Finding Level $levelFilter students...\n";
    $students = $conn->query("SELECT id, name, username, level FROM users WHERE role = 'student' AND level = $levelFilter")->fetch_all(MYSQLI_ASSOC);
} else {
    echo "1. Finding all students...\n";
    $students = $conn->query("SELECT id, name, username, level FROM users WHERE role = 'student'")->fetch_all(MYSQLI_ASSOC);
}

$studentCount = count($students);
echo "   Found $studentCount students\n\n";

if ($studentCount == 0) {
    echo "⚠ No students found. Please create students first.\n";
    closeDBConnection($conn);
    exit(1);
}

// Get all lessons
echo "2. Finding all lessons...\n";
$lessons = $conn->query("SELECT id, lesson_number, title, level FROM lessons ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$lessonCount = count($lessons);
echo "   Found $lessonCount lessons\n\n";

if ($lessonCount == 0) {
    echo "⚠ No lessons found. Please create lessons first.\n";
    closeDBConnection($conn);
    exit(1);
}

// Unlock all lessons for all students
echo "3. Unlocking all lessons for students...\n";
$unlocked = 0;
$alreadyUnlocked = 0;
$errors = 0;
$skipped = 0;

foreach ($students as $student) {
    foreach ($lessons as $lesson) {
        // Skip if lesson level doesn't match student level (unless level filter is 0)
        if ($levelFilter == 0 && $lesson['level'] != $student['level']) {
            $skipped++;
            continue;
        }
        
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
if ($skipped > 0) {
    echo "  Skipped (level mismatch): $skipped\n";
}
if ($errors > 0) {
    echo "  Errors: $errors\n";
}
echo "\n";

if ($levelFilter > 0) {
    echo "All lessons for Level $levelFilter students are now unlocked!\n";
} else {
    echo "All lessons matching each student's level are now unlocked!\n";
    echo "\nNote: Students can only see lessons matching their level.\n";
}
?>
