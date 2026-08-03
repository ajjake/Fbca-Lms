<?php
/**
 * Simple web interface to add a lesson from a file
 * This can be accessed via browser or run from command line
 */

require_once '../config/config.php';
requireRole(['admin', 'teacher']);

$conn = getDBConnection();
$message = '';
$error = '';

// Get subjects
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjectId = $_POST['subject_id'] ?? 0;
    $lessonNumber = $_POST['lesson_number'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $quarter = $_POST['quarter'] ?? 1;
    $level = $_POST['level'] ?? 1;
    $orderIndex = $_POST['order_index'] ?? 0;
    $filePath = $_POST['file_path'] ?? '';
    
    if (empty($subjectId) || empty($lessonNumber) || empty($title)) {
        $error = 'Please fill in all required fields.';
    } elseif (!empty($filePath) && !file_exists($filePath)) {
        $error = 'File not found: ' . htmlspecialchars($filePath);
    } else {
        // Check if lesson number already exists
        $stmt = $conn->prepare("SELECT id FROM lessons WHERE lesson_number = ?");
        $stmt->bind_param("s", $lessonNumber);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Lesson number already exists.';
        } else {
            $materialFile = '';
            
            // If file path provided, copy it
            if (!empty($filePath) && file_exists($filePath)) {
                $fileInfo = pathinfo($filePath);
                $fileName = $fileInfo['basename'];
                $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
                $destinationPath = UPLOAD_PATH_MATERIALS . $newFileName;
                
                if (copy($filePath, $destinationPath)) {
                    $materialFile = $newFileName;
                } else {
                    $error = 'Failed to copy file to uploads directory.';
                }
            }
            
            // Handle uploaded file
            if (empty($error) && isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = UPLOAD_PATH_MATERIALS;
                $fileName = time() . '_' . basename($_FILES['material_file']['name']);
                if (move_uploaded_file($_FILES['material_file']['tmp_name'], $uploadDir . $fileName)) {
                    $materialFile = $fileName;
                }
            }
            
            if (empty($error)) {
                // Insert lesson
                $stmt = $conn->prepare("
                    INSERT INTO lessons (subject_id, lesson_number, title, description, quarter, level, material_file, order_index)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isssiisi", $subjectId, $lessonNumber, $title, $description, $quarter, $level, $materialFile, $orderIndex);
                
                if ($stmt->execute()) {
                    $message = 'Lesson added successfully!';
                    // Clear form
                    $_POST = [];
                } else {
                    $error = 'Failed to add lesson: ' . $conn->error;
                }
                $stmt->close();
            }
        }
        $stmt->close();
    }
}

closeDBConnection($conn);

$pageTitle = 'Add Lesson from File';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Add Lesson from File</h1>
        <a href="<?php echo isAdmin() ? 'users.php' : '../teacher/lessons.php'; ?>" class="btn btn-secondary">Back</a>
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
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label" for="subject_id">Subject *</label>
            <select class="form-control" id="subject_id" name="subject_id" required>
                <option value="">-- Select Subject --</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?php echo $subject['id']; ?>" <?php echo (isset($_POST['subject_id']) && $_POST['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($subject['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="lesson_number">Lesson Number * (e.g., English 1013)</label>
            <input type="text" class="form-control" id="lesson_number" name="lesson_number" 
                   value="<?php echo htmlspecialchars($_POST['lesson_number'] ?? 'English 1013'); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="title">Title *</label>
            <input type="text" class="form-control" id="title" name="title" 
                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label" for="quarter">Quarter *</label>
                <select class="form-control" id="quarter" name="quarter" required>
                    <option value="1" <?php echo (isset($_POST['quarter']) && $_POST['quarter'] == 1) ? 'selected' : ''; ?>>Quarter 1</option>
                    <option value="2" <?php echo (isset($_POST['quarter']) && $_POST['quarter'] == 2) ? 'selected' : ''; ?>>Quarter 2</option>
                    <option value="3" <?php echo (isset($_POST['quarter']) && $_POST['quarter'] == 3) ? 'selected' : ''; ?>>Quarter 3</option>
                    <option value="4" <?php echo (isset($_POST['quarter']) && $_POST['quarter'] == 4) ? 'selected' : ''; ?>>Quarter 4</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="level">Level *</label>
                <input type="number" class="form-control" id="level" name="level" 
                       value="<?php echo htmlspecialchars($_POST['level'] ?? 1); ?>" min="1" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="order_index">Order Index</label>
            <input type="number" class="form-control" id="order_index" name="order_index" 
                   value="<?php echo htmlspecialchars($_POST['order_index'] ?? 0); ?>" min="0">
            <small>Used to order lessons within the same quarter</small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="material_file">Upload Material File (PDF, DOC, DOCX) *</label>
            <input type="file" class="form-control" id="material_file" name="material_file" 
                   accept="application/pdf,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
            <small>Or provide file path below if file is on server</small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="file_path">Or File Path (if file is on server)</label>
            <input type="text" class="form-control" id="file_path" name="file_path" 
                   placeholder="C:\Users\YourName\Documents\English 1013.docx"
                   value="<?php echo htmlspecialchars($_POST['file_path'] ?? ''); ?>">
            <small>Full path to the file on the server. Leave empty if uploading above.</small>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Add Lesson
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
