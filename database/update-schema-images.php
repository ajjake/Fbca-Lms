<?php
/**
 * Update database schema to add image and content fields to lessons
 */

require_once __DIR__ . '/../config/database.php';

echo "Updating database schema...\n";

$conn = getDBConnection();

// Add image field if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM lessons LIKE 'image_file'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE lessons ADD COLUMN image_file VARCHAR(255) NULL AFTER material_file");
    echo "✓ Added image_file column to lessons table\n";
} else {
    echo "✓ image_file column already exists\n";
}

// Add content field if it doesn't exist (for rich text content)
$result = $conn->query("SHOW COLUMNS FROM lessons LIKE 'content'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE lessons ADD COLUMN content LONGTEXT NULL AFTER description");
    echo "✓ Added content column to lessons table\n";
} else {
    echo "✓ content column already exists\n";
}

closeDBConnection($conn);

echo "\nDatabase schema update complete!\n";
?>
