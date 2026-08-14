<?php
$notificationService = new App\Application\NotificationService();
$pendingCount = $notificationService->getPendingExamRequestsCount();
$pendingFinal = $notificationService->getPendingFinalAverageRequestsCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' – ' : ''; ?>FBCA Learning Management System</title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/fbca_logo.png">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body<?php echo (isset($loginPage) && $loginPage) ? ' class="login-page"' : ''; ?>>
    <?php if (isset($loginPage) && $loginPage): ?>
    <style>
        /* Inline login overrides to ensure background and interactivity */
        body.login-page { 
            background: linear-gradient(rgba(6,20,34,0.55), rgba(6,20,34,0.55));
            position: relative;
            overflow: hidden;
        }
        .login-video-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }
        .login-wrapper .card { position: relative; z-index: 3000 !important; pointer-events: auto !important; }
        .nav-overlay, .nav-toggle, .nav-menu { display: none !important; }
        /* hide global footer on login page */
        .footer { display: none !important; }
    </style>
    <video autoplay muted loop class="login-video-bg">
        <source src="<?php echo BASE_URL; ?>assets/bg.mp4" type="video/mp4">
    </video>
<?php endif; ?>
    <?php if (!(isset($loginPage) && $loginPage)): ?>
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?php echo BASE_URL; ?>index.php" class="nav-brand">
                <img src="<?php echo BASE_URL; ?>assets/fbca_logo.png" alt="FBCA" class="nav-logo">
            </a>

            <button type="button" class="nav-toggle" id="nav-toggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>

            <div class="nav-overlay" id="nav-overlay" aria-hidden="true"></div>
            <ul class="nav-menu" id="nav-menu">
                <?php if (isLoggedIn()): ?>
                    <?php if (isStudent()): ?>
                        <li><a href="<?php echo BASE_URL; ?>student/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>student/lessons.php"><i class="fas fa-book"></i> Lessons</a></li>
                        <li><a href="<?php echo BASE_URL; ?>student/grades.php"><i class="fas fa-chart-line"></i> Grades</a></li>
                    <?php elseif (isTeacher()): ?>
                        <li><a href="<?php echo BASE_URL; ?>teacher/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>teacher/lessons.php"><i class="fas fa-book"></i> Lessons</a></li>
                        <li><a href="<?php echo BASE_URL; ?>teacher/students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                        <li><a href="<?php echo BASE_URL; ?>teacher/quizzes.php"><i class="fas fa-question-circle"></i> Quizzes</a></li>
                        <li><a href="<?php echo BASE_URL; ?>teacher/exam-requests.php"><i class="fas fa-clipboard-check"></i> Exam Requests</a></li>
                    <?php elseif (isAdmin()): ?>
                        <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>admin/users.php"><i class="fas fa-users"></i> Users</a></li>
                        <li><a href="<?php echo BASE_URL; ?>admin/lessons.php"><i class="fas fa-book"></i> Lessons</a></li>
                        <li><a href="<?php echo BASE_URL; ?>admin/subjects.php"><i class="fas fa-book-open"></i> Subjects</a></li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>admin/exam-requests.php">
                                <i class="fas fa-bell"></i> Exam Requests
                                <?php if ($pendingCount > 0): ?>
                                    <span class="notification-badge"><?php echo $pendingCount > 99 ? '99+' : $pendingCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>admin/final-requests.php">
                                <i class="fas fa-file-lines"></i> Final Requests
                                <?php if ($pendingFinal > 0): ?>
                                    <span class="notification-badge"><?php echo $pendingFinal > 99 ? '99+' : $pendingFinal; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><a href="<?php echo BASE_URL; ?>admin/reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <?php endif; ?>
                    <li class="nav-user">
                        <div style="position: relative;">
                            <button type="button" class="user-dropdown-trigger" id="user-dropdown-trigger" aria-expanded="false" aria-haspopup="true">
                                <?php if (!empty($_SESSION['avatar'])): ?>
                                    <img src="<?php echo BASE_URL . ltrim($_SESSION['avatar'], '/'); ?>" alt="avatar" style="width:28px; height:28px; object-fit:cover; border-radius:50%; vertical-align:middle; margin-right:8px;">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></span>
                                <i class="fas fa-chevron-down" style="font-size: 0.7rem;"></i>
                            </button>
                            <div class="user-dropdown" id="user-dropdown" role="menu">
                                <a href="<?php echo BASE_URL; ?>logout.php" class="logout-link" role="menuitem"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            </div>
                        </div>
                    </li>
                <?php else: ?>
                    <li><a href="<?php echo BASE_URL; ?>login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <?php endif; ?>
    <main class="main-content">
