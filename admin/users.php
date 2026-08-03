<?php
require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();

$message = '';
$error = '';

// Check for success message from redirect
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// Handle user actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $userId = $_GET['id'];
    
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            $message = 'User deleted successfully.';
        } else {
            $error = 'Failed to delete user.';
        }
        $stmt->close();
    }
}

// Handle add/edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $level = $_POST['level'] ?? 1;
    $lrn = $_POST['lrn'] ?? '';
    $guardian_name = $_POST['guardian_name'] ?? '';
    $guardian_contact = $_POST['guardian_contact'] ?? '';
    
    if (empty($username) || empty($name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } else {
        if ($id > 0) {
            // Update user
            // Ensure optional student fields exist on the table
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS lrn VARCHAR(64) NULL, ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(255) NULL, ADD COLUMN IF NOT EXISTS guardian_contact VARCHAR(50) NULL");
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "UPDATE users SET username = ?, password = ?, name = ?, email = ?, role = ?, level = ?, lrn = ?, guardian_name = ?, guardian_contact = ? WHERE id = ?"
                );
                $stmt->bind_param("sssssisssi", $username, $hashedPassword, $name, $email, $role, $level, $lrn, $guardian_name, $guardian_contact, $id);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE users SET username = ?, name = ?, email = ?, role = ?, level = ?, lrn = ?, guardian_name = ?, guardian_contact = ? WHERE id = ?"
                );
                $stmt->bind_param("sssssisss", $username, $name, $email, $role, $level, $lrn, $guardian_name, $guardian_contact, $id);
            }
        } else {
            // Add new user
            if (empty($password)) {
                $error = 'Password is required for new users.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // Ensure optional student fields exist on the table
                $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS lrn VARCHAR(64) NULL, ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(255) NULL, ADD COLUMN IF NOT EXISTS guardian_contact VARCHAR(50) NULL");
                $stmt = $conn->prepare(
                    "INSERT INTO users (username, password, name, email, role, level, lrn, guardian_name, guardian_contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("sssssisss", $username, $hashedPassword, $name, $email, $role, $level, $lrn, $guardian_name, $guardian_contact);
            }
        }
        
        if (!isset($error) || empty($error)) {
            if ($stmt->execute()) {
                $newUserId = $id > 0 ? $id : $conn->insert_id;
                $stmt->close();

                // Ensure avatar column exists
                $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) NULL");

                // Handle avatar upload (works for both new and edited users)
                if (!empty($_FILES['avatar']) && isset($_FILES['avatar']['tmp_name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
                    $file = $_FILES['avatar'];
                    $maxSize = 2 * 1024 * 1024; // 2MB
                    $imgInfo = @getimagesize($file['tmp_name']);
                    $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
                    if ($imgInfo !== false && isset($allowedTypes[$imgInfo[2]]) && $file['size'] <= $maxSize) {
                        $ext = $allowedTypes[$imgInfo[2]];
                        $uploadDir = __DIR__ . '/../uploads/images/avatars';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $safeName = $newUserId . '_' . time() . '.' . $ext;
                        $target = $uploadDir . '/' . $safeName;
                        if (move_uploaded_file($file['tmp_name'], $target)) {
                            $avatarPath = 'uploads/images/avatars/' . $safeName;
                            $upd = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                            $upd->bind_param("si", $avatarPath, $newUserId);
                            $upd->execute();
                            $upd->close();
                        }
                    }
                }

                // If new student was created, unlock first PACEs
                if ($id == 0 && $role === 'student') {
                    require_once '../includes/pace-unlock.php';
                    
                    // Get all subjects
                    $subjects = $conn->query("SELECT id, code FROM subjects")->fetch_all(MYSQLI_ASSOC);
                    
                    // Unlock first PACE of each quarter for this student
                    foreach ($subjects as $subject) {
                        for ($quarter = 1; $quarter <= 4; $quarter++) {
                            // Calculate first PACE number for this level and quarter
                            $basePace = 1013 + ($level - 1) * 12 + ($quarter - 1) * 3;
                            $paceNumberWithCode = $subject['code'] . ' ' . $basePace;
                            $paceNumberOnly = (string)$basePace;
                            
                            // Find lesson with this PACE number - handle both formats
                            $findStmt = $conn->prepare("
                                SELECT id FROM lessons 
                                WHERE subject_id = ? AND quarter = ? AND level = ?
                                AND (
                                    pace_number = ? OR 
                                    pace_number = ? OR
                                    lesson_number = ? OR
                                    lesson_number = ?
                                )
                                AND pace_type = 'lesson'
                                ORDER BY order_index ASC
                                LIMIT 1
                            ");
                            $findStmt->bind_param("iiissss", $subject['id'], $quarter, $level,
                                $paceNumberWithCode, $paceNumberOnly, $paceNumberWithCode, $paceNumberOnly);
                            $findStmt->execute();
                            $firstPace = $findStmt->get_result()->fetch_assoc();
                            $findStmt->close();
                            
                            if ($firstPace) {
                                // Unlock first PACE
                                $unlockStmt = $conn->prepare("
                                    INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                                    VALUES (?, ?, 'unlocked', NOW())
                                    ON DUPLICATE KEY UPDATE status = 'unlocked', unlocked_at = NOW()
                                ");
                                $unlockStmt->bind_param("ii", $newUserId, $firstPace['id']);
                                $unlockStmt->execute();
                                $unlockStmt->close();
                            }
                        }
                    }
                }
                
                closeDBConnection($conn);
                // Redirect to show success message and close form
                header('Location: users.php?msg=' . urlencode($id > 0 ? 'User updated successfully.' : 'User added successfully.'));
                exit();
            } else {
                // Check for specific database errors
                $dbError = $conn->error;
                if (strpos($dbError, 'Duplicate entry') !== false) {
                    if (strpos($dbError, 'username') !== false) {
                        $error = 'Username already exists. Please choose a different username.';
                    } elseif (strpos($dbError, 'email') !== false) {
                        $error = 'Email already exists. Please use a different email address.';
                    } else {
                        $error = 'A user with this information already exists.';
                    }
                } else {
                    $error = 'Failed to save user: ' . htmlspecialchars($dbError);
                }
            }

                // If role is teacher, handle subject and level assignments
                if ($role === 'teacher') {
                    // create teacher_levels table if needed
                    $conn->query("CREATE TABLE IF NOT EXISTS teacher_levels (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        teacher_id INT NOT NULL,
                        level INT NOT NULL,
                        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
                        UNIQUE KEY unique_teacher_level (teacher_id, level)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                    // Remove existing subject assignments
                    $del = $conn->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?");
                    $del->bind_param("i", $newUserId);
                    $del->execute();
                    $del->close();

                    // Insert new subject assignments if provided
                    if (!empty($_POST['subjects']) && is_array($_POST['subjects'])) {
                        foreach ($_POST['subjects'] as $subId) {
                            $sId = (int)$subId;
                            $ins = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                            $ins->bind_param("ii", $newUserId, $sId);
                            $ins->execute();
                            $ins->close();
                        }
                    }

                    // Remove existing level assignments
                    $delL = $conn->prepare("DELETE FROM teacher_levels WHERE teacher_id = ?");
                    $delL->bind_param("i", $newUserId);
                    $delL->execute();
                    $delL->close();

                    // Insert new level assignments if provided
                    if (!empty($_POST['levels']) && is_array($_POST['levels'])) {
                        foreach ($_POST['levels'] as $lv) {
                            $lvl = (int)$lv;
                            $insL = $conn->prepare("INSERT INTO teacher_levels (teacher_id, level) VALUES (?, ?)");
                            $insL->bind_param("ii", $newUserId, $lvl);
                            $insL->execute();
                            $insL->close();
                        }
                    }
                } else {
                    // If user is not teacher, remove any existing teacher assignments
                    $del = $conn->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?");
                    $del->bind_param("i", $newUserId);
                    $del->execute();
                    $del->close();
                    $delL = $conn->prepare("DELETE FROM teacher_levels WHERE teacher_id = ?");
                    $delL->bind_param("i", $newUserId);
                    $delL->execute();
                    $delL->close();
                }
            $stmt->close();
        }
    }
}

// Get users
$roleFilter = $_GET['role'] ?? 'all';
$query = "SELECT * FROM users";
if ($roleFilter !== 'all') {
    $query .= " WHERE role = '" . $conn->real_escape_string($roleFilter) . "'";
}
$query .= " ORDER BY created_at DESC";
$users = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get all subjects for assignment UI
$subjectsList = $conn->query("SELECT id, name, code FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get available levels from students
$levelsList = $conn->query("SELECT DISTINCT level FROM users WHERE role = 'student' ORDER BY level")->fetch_all(MYSQLI_ASSOC);

// Get user to edit or check if adding new user
$editUser = null;
$showForm = false;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    // load assigned subjects and levels for this teacher
    if ($editUser && $editUser['role'] === 'teacher') {
        $stmt2 = $conn->prepare("SELECT subject_id FROM teacher_subjects WHERE teacher_id = ?");
        $stmt2->bind_param("i", $editId);
        $stmt2->execute();
        $assigned = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $assignedIds = array_column($assigned, 'subject_id');
        $stmt2->close();

        $stmt3 = $conn->prepare("SELECT level FROM teacher_levels WHERE teacher_id = ?");
        $stmt3->bind_param("i", $editId);
        $stmt3->execute();
        $assignedLv = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
        $assignedLevelIds = array_column($assignedLv, 'level');
        $stmt3->close();
    } else {
        $assignedIds = [];
        $assignedLevelIds = [];
    }
    $showForm = true;
} elseif (isset($_GET['add'])) {
    $showForm = true;
}

closeDBConnection($conn);

$pageTitle = 'Manage Users';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Manage Users</h1>
        <button onclick="showAddForm()" class="btn btn-primary">Add New User</button>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <div style="margin-bottom: 1.5rem;">
        <a href="?role=all" class="btn <?php echo $roleFilter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
        <a href="?role=student" class="btn <?php echo $roleFilter === 'student' ? 'btn-primary' : 'btn-secondary'; ?>">Students</a>
        <a href="?role=teacher" class="btn <?php echo $roleFilter === 'teacher' ? 'btn-primary' : 'btn-secondary'; ?>">Teachers</a>
        <a href="?role=admin" class="btn <?php echo $roleFilter === 'admin' ? 'btn-primary' : 'btn-secondary'; ?>">Admins</a>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Level</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge badge-info"><?php echo ucfirst($user['role']); ?></span></td>
                        <td><?php echo $user['level']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php if ($user['role'] !== 'admin'): ?>
                                <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this user?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'teacher'): ?>
                                <a href="teacher-assign.php?teacher_id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-user-tag"></i> Assign Subjects
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit User Form -->
<div id="userFormModal" style="display: <?php echo $showForm ? 'flex' : 'none'; ?>; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="max-width: 600px; margin: 2rem; max-height: 90vh; overflow-y: auto;">
        <div class="card-header">
            <h2 class="card-title"><?php echo $editUser ? 'Edit User' : 'Add New User'; ?></h2>
            <button onclick="hideForm()" class="btn btn-secondary btn-sm">Close</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $editUser['id'] ?? 0; ?>">
            
            <div class="form-group">
                <label class="form-label" for="username">Username *</label>
                <input type="text" class="form-control" id="username" name="username" 
                       value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password <?php echo $editUser ? '(leave blank to keep current)' : '*'; ?></label>
                <input type="password" class="form-control" id="password" name="password" 
                       <?php echo $editUser ? '' : 'required'; ?>>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="name">Full Name *</label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?php echo htmlspecialchars($editUser['name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="email">Email *</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>" required>
            </div>

            <div id="avatar-field" class="form-group" style="display: <?php echo in_array(($editUser['role'] ?? 'student'), ['student','teacher']) ? 'block' : 'none'; ?>;">
                <label class="form-label" for="avatar">Photo</label>
                <?php if (!empty($editUser['avatar'])): ?>
                    <div style="margin-bottom:0.5rem;"><img src="<?php echo htmlspecialchars($editUser['avatar']); ?>" alt="avatar" style="max-width:96px; max-height:96px; border-radius:6px; border:1px solid #ddd;"></div>
                <?php endif; ?>
                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                <small class="form-help">Upload a JPG/PNG/GIF image. Max 2MB.</small>
            </div>

            <div id="student-details" style="display: <?php echo (($editUser['role'] ?? 'student') === 'student') ? 'block' : 'none'; ?>; margin-top:1rem;">
                <h3 style="margin-bottom:0.5rem;">Student Details</h3>
                <div class="form-group">
                    <label class="form-label" for="lrn">LRN</label>
                    <input type="text" class="form-control" id="lrn" name="lrn" value="<?php echo htmlspecialchars($editUser['lrn'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="guardian_name">Guardian Name</label>
                    <input type="text" class="form-control" id="guardian_name" name="guardian_name" value="<?php echo htmlspecialchars($editUser['guardian_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="guardian_contact">Guardian Contact</label>
                    <input type="text" class="form-control" id="guardian_contact" name="guardian_contact" value="<?php echo htmlspecialchars($editUser['guardian_contact'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="role">Role *</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="student" <?php echo ($editUser['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="teacher" <?php echo ($editUser['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="admin" <?php echo ($editUser['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="level">Level</label>
                    <input type="number" class="form-control" id="level" name="level" 
                           value="<?php echo $editUser['level'] ?? 1; ?>" min="1" required>
                </div>
            </div>

            <div id="teacher-assignments" style="display: <?php echo (($editUser['role'] ?? '') === 'teacher') ? 'block' : 'none'; ?>; margin-top:1rem;">
                <h3 style="margin-bottom:0.5rem;">Teacher Assignments</h3>
                <div class="form-group">
                    <label class="form-label">Subjects</label>
                    <div style="max-height: 200px; overflow-y: auto; border:1px solid #ddd; padding:0.75rem; border-radius:6px;">
                        <?php foreach ($subjectsList as $sub): ?>
                            <div style="margin-bottom:0.4rem;">
                                <label>
                                    <input type="checkbox" name="subjects[]" value="<?php echo $sub['id']; ?>" <?php echo in_array($sub['id'], $assignedIds ?? []) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($sub['name']); ?> (<?php echo htmlspecialchars($sub['code']); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-top:0.75rem;">
                    <label class="form-label">Levels</label>
                    <div style="max-height: 160px; overflow-y: auto; border:1px solid #ddd; padding:0.75rem; border-radius:6px;">
                        <?php foreach ($levelsList as $lv): ?>
                            <?php $lvl = (int)$lv['level']; ?>
                            <div style="margin-bottom:0.4rem;">
                                <label>
                                    <input type="checkbox" name="levels[]" value="<?php echo $lvl; ?>" <?php echo in_array($lvl, $assignedLevelIds ?? []) ? 'checked' : ''; ?>>
                                    Level <?php echo $lvl; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save User
            </button>
        </form>
    </div>
</div>

<script>
function showAddForm() {
    window.location.href = 'users.php?add=1';
}

function hideForm() {
    window.location.href = 'users.php';
}
// Toggle teacher assignments when role changes
document.addEventListener('DOMContentLoaded', function() {
    var roleSelect = document.getElementById('role');
    var assignBox = document.getElementById('teacher-assignments');
    var avatarField = document.getElementById('avatar-field');
    var studentDetails = document.getElementById('student-details');
    if (!roleSelect || !assignBox) return;
    roleSelect.addEventListener('change', function() {
        if (roleSelect.value === 'teacher') assignBox.style.display = 'block';
        else assignBox.style.display = 'none';
        if (avatarField) {
            if (roleSelect.value === 'student' || roleSelect.value === 'teacher') avatarField.style.display = 'block';
            else avatarField.style.display = 'none';
        }
        if (studentDetails) {
            if (roleSelect.value === 'student') studentDetails.style.display = 'block';
            else studentDetails.style.display = 'none';
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
