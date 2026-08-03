<?php
require_once 'config/config.php';

// Redirect based on login status and role
if (isLoggedIn()) {
    if (isStudent()) {
        header('Location: ' . BASE_URL . 'student/dashboard.php');
    } elseif (isTeacher()) {
        header('Location: ' . BASE_URL . 'teacher/dashboard.php');
    } elseif (isAdmin()) {
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
    }
} else {
    header('Location: ' . BASE_URL . 'login.php');
}
exit();
?>
