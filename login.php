<?php
use App\Application\AuthService;

require_once 'config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isStudent()) {
        header('Location: ' . BASE_URL . 'student/dashboard.php');
    } elseif (isTeacher()) {
        header('Location: ' . BASE_URL . 'teacher/dashboard.php');
    } elseif (isAdmin()) {
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
    }
    exit();
}

$error = '';
$authService = new AuthService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username !== '' && $password !== '') {
        if ($authService->login($username, $password)) {
            if (isStudent()) {
                header('Location: ' . BASE_URL . 'student/dashboard.php');
            } elseif (isTeacher()) {
                header('Location: ' . BASE_URL . 'teacher/dashboard.php');
            } elseif (isAdmin()) {
                header('Location: ' . BASE_URL . 'admin/dashboard.php');
            }
            exit();
        }

        $error = 'Invalid username or password.';
    } else {
        $error = 'Please enter both username and password.';
    }
}

$pageTitle = 'Login';
$loginPage = true;
include 'includes/header.php';
?>

<div class="login-wrapper">
    <div class="card">
        <div class="card-header" style="flex-direction:column; align-items:center; gap:8px;">
            <img src="<?php echo BASE_URL; ?>assets/fbca_logo.png" alt="FBCA" class="login-logo">
            <h1 class="card-title login-title">FBCA LEARNING MANAGEMENT SYSTEM</h1>
            <p class="login-subtitle">Sign in to your account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="login-form">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus placeholder="Enter username">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Enter password">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Sign in
            </button>
        </form>
        
        
    </div>
</div>

<?php include 'includes/footer.php'; ?>
