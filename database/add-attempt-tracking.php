<?php
/**
 * Add attempt tracking to lesson_scores table
 */

require_once __DIR__ . '/../config/database.php';

echo "Adding attempt tracking to lesson_scores...\n";

$conn = getDBConnection();

// Add attempt_number column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM lesson_scores LIKE 'attempt_number'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE lesson_scores ADD COLUMN attempt_number INT DEFAULT 1 AFTER passed");
    echo "✓ Added attempt_number column to lesson_scores table\n";
    
    // Set existing records to attempt 1
    $conn->query("UPDATE lesson_scores SET attempt_number = 1 WHERE attempt_number IS NULL");
    echo "✓ Set existing records to attempt 1\n";
} else {
    echo "✓ attempt_number column already exists\n";
}

// Add best_score column to track the best score across attempts
$result = $conn->query("SHOW COLUMNS FROM lesson_scores LIKE 'is_best_score'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE lesson_scores ADD COLUMN is_best_score BOOLEAN DEFAULT FALSE AFTER attempt_number");
    echo "✓ Added is_best_score column to lesson_scores table\n";
} else {
    echo "✓ is_best_score column already exists\n";
}

closeDBConnection($conn);

echo "\nDatabase update complete!\n";
?>
