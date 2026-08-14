<?php
/**
 * Database Setup Script
 * This script creates the database and imports the schema
 * Run this once to set up your database
 */

// Database configuration (without database name)
$host = 'localhost';
$user = 'root';
$pass = '';

// Database name
$dbname = 'fbcals_db';

echo "FBCA LMS Database Setup\n";
echo "=======================\n\n";

try {
    // Step 1: Connect to MySQL server (without database)
    echo "1. Connecting to MySQL server...\n";
    $conn = new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error . "\n");
    }
    echo "   ✓ Connected successfully\n\n";
    
    // Step 2: Create database if it doesn't exist
    echo "2. Creating database '$dbname'...\n";
    $sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        echo "   ✓ Database created or already exists\n\n";
    } else {
        die("   ✗ Error creating database: " . $conn->error . "\n");
    }
    
    // Step 3: Select the database
    echo "3. Selecting database...\n";
    $conn->select_db($dbname);
    echo "   ✓ Database selected\n\n";
    
    // Step 4: Read and execute schema file
    echo "4. Importing schema...\n";
    $schemaFile = __DIR__ . '/database/schema.sql';
    
    if (!file_exists($schemaFile)) {
        die("   ✗ Schema file not found: $schemaFile\n");
    }
    
    $schema = file_get_contents($schemaFile);
    
    // Remove comments and split by semicolon
    $schema = preg_replace('/--.*$/m', '', $schema);
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) {
            continue;
        }
        
        // Skip CREATE DATABASE statements
        if (stripos($statement, 'CREATE DATABASE') !== false) {
            continue;
        }
        
        if ($conn->query($statement) === TRUE) {
            $successCount++;
        } else {
            // Ignore "table already exists" errors
            if (stripos($conn->error, 'already exists') === false && 
                stripos($conn->error, 'Duplicate entry') === false) {
                echo "   ⚠ Warning: " . $conn->error . "\n";
                $errorCount++;
            }
        }
    }
    
    echo "   ✓ Schema imported successfully ($successCount statements executed)\n";
    if ($errorCount > 0) {
        echo "   ⚠ $errorCount warnings (some tables may already exist)\n";
    }
    echo "\n";
    
    // Step 5: Verify tables were created
    echo "5. Verifying database structure...\n";
    $tables = [
        'users', 'subjects', 'teacher_subjects', 'lessons', 'quizzes',
        'quiz_questions', 'student_progress', 'exam_requests', 'lesson_scores',
        'quarter_exams', 'quarter_exam_questions', 'quarter_exam_scores',
        'quarter_grades', 'final_grades'
    ];
    
    $existingTables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $existingTables[] = $row[0];
    }
    
    $missingTables = array_diff($tables, $existingTables);
    
    if (empty($missingTables)) {
        echo "   ✓ All tables created successfully\n\n";
    } else {
        echo "   ⚠ Missing tables: " . implode(', ', $missingTables) . "\n\n";
    }
    
    // Step 6: Check for default data
    echo "6. Checking default data...\n";
    $userResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $userRow = $userResult->fetch_assoc();
    
    if ($userRow['count'] > 0) {
        echo "   ✓ Default admin user exists\n";
    } else {
        echo "   ⚠ No admin user found (check schema.sql)\n";
    }
    
    $subjectResult = $conn->query("SELECT COUNT(*) as count FROM subjects");
    $subjectRow = $subjectResult->fetch_assoc();
    
    if ($subjectRow['count'] > 0) {
        echo "   ✓ Default subjects created (" . $subjectRow['count'] . " subjects)\n";
    } else {
        echo "   ⚠ No subjects found (check schema.sql)\n";
    }
    
    echo "\n";
    
    // Close connection
    $conn->close();
    
    echo "========================================\n";
    echo "✓ Database setup completed successfully!\n";
    echo "========================================\n\n";
    echo "Default Admin Credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n\n";
    echo "⚠ IMPORTANT: Change the admin password after first login!\n";
    echo "\nYou can now access the system at:\n";
    echo "  http://localhost/FBCA%20Web%20System/login.php\n";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
