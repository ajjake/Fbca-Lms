<?php
/**
 * Script to add English 1013 lesson from file
 */

require_once 'config/config.php';
require_once 'config/database.php';

$filePath = "C:\\Users\\Acer Swift 3\\Documents\\Eng 1013.docx";
$lessonNumber = "English 1013";
$title = "English 1013";
$subjectName = "English";
$quarter = 1;
$level = 1;
$orderIndex = 0;

echo "FBCA LMS - Adding English 1013 Lesson\n";
echo "======================================\n\n";

// Check if file exists
if (!file_exists($filePath)) {
    die("Error: File not found: $filePath\n");
}

echo "1. File found: $filePath\n";
$fileInfo = pathinfo($filePath);
$fileName = $fileInfo['basename'];
echo "   File name: $fileName\n\n";

// Connect to database
$conn = getDBConnection();

// Get English subject
$stmt = $conn->prepare("SELECT id, name, code FROM subjects WHERE name = ? OR code = ?");
$stmt->bind_param("ss", $subjectName, $subjectName);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subject) {
    closeDBConnection($conn);
    die("Error: Subject 'English' not found in database.\n");
}

$subjectId = $subject['id'];
echo "2. Subject found: {$subject['name']} ({$subject['code']}) - ID: $subjectId\n\n";

// Check if lesson number already exists
$stmt = $conn->prepare("SELECT id, title FROM lessons WHERE lesson_number = ?");
$stmt->bind_param("s", $lessonNumber);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    closeDBConnection($conn);
    die("Error: Lesson number '$lessonNumber' already exists (ID: {$existing['id']}, Title: {$existing['title']}).\n");
}

// Copy file to uploads directory
echo "3. Copying file to uploads directory...\n";
$uploadDir = UPLOAD_PATH_MATERIALS;
$newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
$destinationPath = $uploadDir . $newFileName;

if (!copy($filePath, $destinationPath)) {
    closeDBConnection($conn);
    die("Error: Failed to copy file to uploads directory.\n");
}

echo "   ✓ File copied to: $destinationPath\n";
echo "   Saved as: $newFileName\n\n";

// Insert lesson into database
echo "4. Adding lesson to database...\n";
$description = "English lesson 1013";
$stmt = $conn->prepare("
    INSERT INTO lessons (subject_id, lesson_number, title, description, quarter, level, material_file, order_index)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("isssiisi", $subjectId, $lessonNumber, $title, $description, $quarter, $level, $newFileName, $orderIndex);

if ($stmt->execute()) {
    $lessonId = $conn->insert_id;
    echo "   ✓ Lesson added successfully!\n";
    echo "   Lesson ID: $lessonId\n\n";
    
    echo "========================================\n";
    echo "✓ Lesson added successfully!\n";
    echo "========================================\n\n";
    echo "Lesson Details:\n";
    echo "  Lesson ID: $lessonId\n";
    echo "  Lesson Number: $lessonNumber\n";
    echo "  Title: $title\n";
    echo "  Subject: {$subject['name']} ({$subject['code']})\n";
    echo "  Quarter: $quarter\n";
    echo "  Level: $level\n";
    echo "  Material File: $newFileName\n";
    echo "  Original File: $fileName\n\n";
    
    echo "You can now view this lesson in the teacher/admin panel.\n";
} else {
    // Delete uploaded file if database insert failed
    if (file_exists($destinationPath)) {
        unlink($destinationPath);
    }
    closeDBConnection($conn);
    die("Error: Failed to add lesson to database: " . $conn->error . "\n");
}

$stmt->close();
closeDBConnection($conn);
?>
