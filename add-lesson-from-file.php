<?php
/**
 * Script to add a lesson from a Word document file
 * Usage: php add-lesson-from-file.php "path/to/file.docx"
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Check if file path is provided
if ($argc < 2) {
    echo "Usage: php add-lesson-from-file.php \"path/to/English 1013.docx\" [subject_id] [quarter] [level]\n";
    echo "\n";
    echo "Example: php add-lesson-from-file.php \"C:\\Users\\YourName\\Documents\\English 1013.docx\" 1 1 1\n";
    echo "\n";
    echo "If subject_id, quarter, or level are not provided, defaults will be used:\n";
    echo "  - Subject: English (ID 1)\n";
    echo "  - Quarter: 1\n";
    echo "  - Level: 1\n";
    exit(1);
}

$filePath = $argv[1];
$subjectId = isset($argv[2]) ? (int)$argv[2] : 1; // Default to English
$quarter = isset($argv[3]) ? (int)$argv[3] : 1;
$level = isset($argv[4]) ? (int)$argv[4] : 1;

echo "FBCA LMS - Add Lesson from File\n";
echo "================================\n\n";

// Check if file exists
if (!file_exists($filePath)) {
    die("Error: File not found: $filePath\n");
}

echo "1. File found: $filePath\n";
$fileInfo = pathinfo($filePath);
$fileName = $fileInfo['basename'];
$fileExtension = strtolower($fileInfo['extension'] ?? '');

// Validate file extension
$allowedExtensions = ['doc', 'docx', 'pdf'];
if (!in_array($fileExtension, $allowedExtensions)) {
    die("Error: File must be .doc, .docx, or .pdf. Found: .$fileExtension\n");
}

echo "   File name: $fileName\n";
echo "   File type: .$fileExtension\n\n";

// Extract lesson number from filename (e.g., "English 1013" from "English 1013.docx")
$lessonNumber = '';
if (preg_match('/(English|ENG)\s*(\d+)/i', $fileName, $matches)) {
    $lessonNumber = 'English ' . $matches[2];
} else {
    // Try to extract any number
    if (preg_match('/(\d{4})/', $fileName, $matches)) {
        $lessonNumber = 'English ' . $matches[1];
    } else {
        $lessonNumber = 'English 1013'; // Default
    }
}

echo "2. Extracted lesson number: $lessonNumber\n\n";

// Connect to database
$conn = getDBConnection();

// Get subject info
$stmt = $conn->prepare("SELECT id, name, code FROM subjects WHERE id = ?");
$stmt->bind_param("i", $subjectId);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subject) {
    die("Error: Subject with ID $subjectId not found.\n");
}

echo "3. Subject: {$subject['name']} ({$subject['code']})\n";
echo "   Quarter: $quarter\n";
echo "   Level: $level\n\n";

// Check if lesson number already exists
$stmt = $conn->prepare("SELECT id FROM lessons WHERE lesson_number = ?");
$stmt->bind_param("s", $lessonNumber);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $stmt->close();
    closeDBConnection($conn);
    die("Error: Lesson number '$lessonNumber' already exists.\n");
}
$stmt->close();

// Copy file to uploads directory
echo "4. Copying file to uploads directory...\n";
$uploadDir = UPLOAD_PATH_MATERIALS;
$newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
$destinationPath = $uploadDir . $newFileName;

if (!copy($filePath, $destinationPath)) {
    closeDBConnection($conn);
    die("Error: Failed to copy file to uploads directory.\n");
}

echo "   ✓ File copied to: $destinationPath\n\n";

// Prompt for lesson details
echo "5. Please provide lesson details:\n";
echo "   (Press Enter to use defaults)\n\n";

// Get title
echo "   Lesson Title (default: '$lessonNumber'): ";
$title = trim(fgets(STDIN));
if (empty($title)) {
    $title = $lessonNumber;
}

// Get description
echo "   Description (optional, press Enter to skip): ";
$description = trim(fgets(STDIN));

// Get order index
echo "   Order Index (default: 0): ";
$orderIndexInput = trim(fgets(STDIN));
$orderIndex = empty($orderIndexInput) ? 0 : (int)$orderIndexInput;

echo "\n";

// Insert lesson into database
echo "6. Adding lesson to database...\n";
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
    echo "  Lesson Number: $lessonNumber\n";
    echo "  Title: $title\n";
    echo "  Subject: {$subject['name']}\n";
    echo "  Quarter: $quarter\n";
    echo "  Level: $level\n";
    echo "  Material File: $newFileName\n\n";
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

echo "You can now view this lesson in the teacher panel.\n";
?>
