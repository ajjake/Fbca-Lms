<?php
/**
 * Fix Admin Password Script
 * This script updates the admin password with a correct hash
 */

require_once 'config/database.php';

echo "FBCA LMS - Fix Admin Password\n";
echo "==============================\n\n";

try {
    $conn = getDBConnection();
    
    // Check current admin user
    echo "1. Checking admin user...\n";
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die("   ✗ Admin user not found!\n");
    }
    
    $admin = $result->fetch_assoc();
    echo "   ✓ Admin user found (ID: {$admin['id']})\n";
    echo "   Current password hash: " . substr($admin['password'], 0, 20) . "...\n\n";
    
    // Generate new password hash for 'admin123'
    echo "2. Generating new password hash for 'admin123'...\n";
    $newPassword = 'admin123';
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    echo "   ✓ New hash generated\n";
    echo "   Hash: " . substr($newHash, 0, 30) . "...\n\n";
    
    // Verify the hash works
    echo "3. Verifying hash...\n";
    if (password_verify($newPassword, $newHash)) {
        echo "   ✓ Hash verification successful\n\n";
    } else {
        die("   ✗ Hash verification failed!\n");
    }
    
    // Update the password
    echo "4. Updating admin password in database...\n";
    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $updateStmt->bind_param("s", $newHash);
    
    if ($updateStmt->execute()) {
        echo "   ✓ Password updated successfully\n\n";
    } else {
        die("   ✗ Error updating password: " . $conn->error . "\n");
    }
    
    // Verify the update
    echo "5. Verifying updated password...\n";
    $verifyStmt = $conn->prepare("SELECT password FROM users WHERE username = 'admin'");
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $updatedAdmin = $verifyResult->fetch_assoc();
    
    if (password_verify($newPassword, $updatedAdmin['password'])) {
        echo "   ✓ Password verification successful!\n\n";
    } else {
        die("   ✗ Password verification failed after update!\n");
    }
    
    $updateStmt->close();
    $verifyStmt->close();
    $stmt->close();
    closeDBConnection($conn);
    
    echo "========================================\n";
    echo "✓ Admin password fixed successfully!\n";
    echo "========================================\n\n";
    echo "You can now login with:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n\n";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
