<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, password, name, email, role, level FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['level'] = $user['level'];
                // Load avatar into session if column exists
                $check = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
                if ($check && $check->num_rows > 0) {
                    $ast = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
                    $ast->bind_param("i", $user['id']);
                    $ast->execute();
                    $aRow = $ast->get_result()->fetch_assoc();
                    $ast->close();
                    $_SESSION['avatar'] = $aRow['avatar'] ?? null;
                }
                
                // Redirect based on role
                if ($user['role'] === 'student') {
                    header('Location: ' . BASE_URL . 'student/dashboard.php');
                } elseif ($user['role'] === 'teacher') {
                    header('Location: ' . BASE_URL . 'teacher/dashboard.php');
                } elseif ($user['role'] === 'admin') {
                    header('Location: ' . BASE_URL . 'admin/dashboard.php');
                }
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
        
        $stmt->close();
        closeDBConnection($conn);
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
