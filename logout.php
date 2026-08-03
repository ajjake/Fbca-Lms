<?php
require_once 'config/config.php';

// Destroy session
session_destroy();

// Redirect to login
header('Location: ' . BASE_URL . 'login.php');
exit();
?>
