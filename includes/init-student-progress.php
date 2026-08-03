<?php
/**
 * Initialize Student Progress Script
 * 
 * This script should be run once to initialize lesson progress for existing students.
 * It unlocks the first lesson of each subject for all students.
 * 
 * Usage: Run this script once after setting up the system or adding new students.
 */

require_once __DIR__ . '/../config/config.php';

if (!isAdmin()) {
    die('Access denied. Admin privileges required.');
}

$conn = getDBConnection();

// Get all students
$students = $conn->query("SELECT id, level FROM users WHERE role = 'student'")->fetch_all(MYSQLI_ASSOC);

// Get all subjects
$subjects = $conn->query("SELECT id FROM subjects")->fetch_all(MYSQLI_ASSOC);

$unlocked = 0;
$errors = 0;

foreach ($students as $student) {
    foreach ($subjects as $subject) {
        // Find the first lesson (lowest order_index) for this subject and student's level
        $stmt = $conn->prepare("
            SELECT id FROM lessons 
            WHERE subject_id = ? AND level = ? 
            ORDER BY quarter, order_index ASC 
            LIMIT 1
        ");
        $stmt->bind_param("ii", $subject['id'], $student['level']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $lesson = $result->fetch_assoc();
            $lessonId = $lesson['id'];
            
            // Check if progress already exists
            $checkStmt = $conn->prepare("
                SELECT id FROM student_progress 
                WHERE student_id = ? AND lesson_id = ?
            ");
            $checkStmt->bind_param("ii", $student['id'], $lessonId);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows == 0) {
                // Insert unlocked status
                $insertStmt = $conn->prepare("
                    INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                    VALUES (?, ?, 'unlocked', NOW())
                ");
                $insertStmt->bind_param("ii", $student['id'], $lessonId);
                
                if ($insertStmt->execute()) {
                    $unlocked++;
                } else {
                    $errors++;
                }
                $insertStmt->close();
            }
            $checkStmt->close();
        }
        $stmt->close();
    }
}

closeDBConnection($conn);

echo "Initialization complete!\n";
echo "Unlocked lessons: $unlocked\n";
if ($errors > 0) {
    echo "Errors: $errors\n";
}
?>
