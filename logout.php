<?php
require_once 'config/config.php';

logout();

header('Location: ' . BASE_URL . 'login.php');
exit();
?>
