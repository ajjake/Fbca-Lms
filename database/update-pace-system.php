<?php
/**
 * Update database to support PACE system
 * - Add pace_number column (or rename lesson_number)
 * - Add pace_type column (lesson, monthly_test, quarter_test)
 */

require_once __DIR__ . '/../config/database.php';

echo "Updating database for PACE system...\n";

$conn = getDBConnection();

// Check if pace_number column exists
$result = $conn->query("SHOW COLUMNS FROM lessons LIKE 'pace_number'");
if ($result->num_rows == 0) {
    // Add pace_number column
    $conn->query("ALTER TABLE lessons ADD COLUMN pace_number VARCHAR(20) NULL AFTER lesson_number");
    echo "✓ Added pace_number column\n";
    
    // Copy lesson_number to pace_number for existing records
    $conn->query("UPDATE lessons SET pace_number = lesson_number WHERE pace_number IS NULL");
    echo "✓ Copied existing lesson numbers to pace numbers\n";
} else {
    echo "✓ pace_number column already exists\n";
}

// Add pace_type column
$result = $conn->query("SHOW COLUMNS FROM lessons LIKE 'pace_type'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE lessons ADD COLUMN pace_type ENUM('lesson', 'monthly_test', 'quarter_test') DEFAULT 'lesson' AFTER pace_number");
    echo "✓ Added pace_type column\n";
} else {
    echo "✓ pace_type column already exists\n";
}

// Add monthly_test_id and quarter_test_id to track test relationships
$result = $conn->query("SHOW COLUMNS FROM lessons LIKE 'monthly_test_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE lessons ADD COLUMN monthly_test_id INT NULL AFTER pace_type");
    $conn->query("ALTER TABLE lessons ADD COLUMN quarter_test_id INT NULL AFTER monthly_test_id");
    echo "✓ Added test relationship columns\n";
} else {
    echo "✓ Test relationship columns already exist\n";
}

closeDBConnection($conn);

echo "\nDatabase update complete!\n";
?>
