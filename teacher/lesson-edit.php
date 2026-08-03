<?php
require_once '../config/config.php';
requireRole(['teacher']);

$lessonId = $_GET['id'] ?? 0;
if (!$lessonId) {
    header('Location: lessons.php');
    exit();
}

$conn = getDBConnection();
$teacherId = getCurrentUserId();

// Verify lesson belongs to teacher's subject
$stmt = $conn->prepare("
    SELECT l.*, s.name as subject_name
    FROM lessons l
    INNER JOIN subjects s ON l.subject_id = s.id
    INNER JOIN teacher_subjects ts ON l.subject_id = ts.subject_id
    WHERE l.id = ? AND ts.teacher_id = ?
");
$stmt->bind_param("ii", $lessonId, $teacherId);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lesson) {
    header('Location: lessons.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lessonNumber = $_POST['lesson_number'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $quarter = $_POST['quarter'] ?? 1;
    $level = $_POST['level'] ?? 1;
    $videoUrl = $_POST['video_url'] ?? '';
    $orderIndex = $_POST['order_index'] ?? 0;
    
    if (empty($lessonNumber) || empty($title)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Handle file uploads
        $videoFile = $lesson['video_file'];
        $materialFile = $lesson['material_file'];
        
        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            // Delete old file if exists
            if ($videoFile && file_exists(UPLOAD_PATH_VIDEOS . $videoFile)) {
                unlink(UPLOAD_PATH_VIDEOS . $videoFile);
            }
            
            $uploadDir = UPLOAD_PATH_VIDEOS;
            $fileName = time() . '_' . basename($_FILES['video_file']['name']);
            if (move_uploaded_file($_FILES['video_file']['tmp_name'], $uploadDir . $fileName)) {
                $videoFile = $fileName;
            }
        }
        
        if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
            // Delete old file if exists
            if ($materialFile && file_exists(UPLOAD_PATH_MATERIALS . $materialFile)) {
                unlink(UPLOAD_PATH_MATERIALS . $materialFile);
            }
            
            $uploadDir = UPLOAD_PATH_MATERIALS;
            $fileName = time() . '_' . basename($_FILES['material_file']['name']);
            if (move_uploaded_file($_FILES['material_file']['tmp_name'], $uploadDir . $fileName)) {
                $materialFile = $fileName;
            }
        }
        
        // Update lesson
        $stmt = $conn->prepare("
            UPDATE lessons 
            SET lesson_number = ?, title = ?, description = ?, quarter = ?, level = ?, 
                video_url = ?, video_file = ?, material_file = ?, order_index = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssiisssii", $lessonNumber, $title, $description, $quarter, $level, 
                         $videoUrl, $videoFile, $materialFile, $orderIndex, $lessonId);
        
        if ($stmt->execute()) {
            $message = 'Lesson updated successfully!';
            // Reload lesson data
            $stmt = $conn->prepare("
                SELECT l.*, s.name as subject_name
                FROM lessons l
                INNER JOIN subjects s ON l.subject_id = s.id
                WHERE l.id = ?
            ");
            $stmt->bind_param("i", $lessonId);
            $stmt->execute();
            $lesson = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $error = 'Failed to update lesson.';
        }
        $stmt->close();
    }
}

closeDBConnection($conn);

$pageTitle = 'Edit Lesson';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Edit Lesson</h1>
        <a href="lessons.php?subject_id=<?php echo $lesson['subject_id']; ?>" class="btn btn-secondary">Back</a>
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
            <label class="form-label" for="lesson_number">Lesson Number *</label>
            <input type="text" class="form-control" id="lesson_number" name="lesson_number" 
                   value="<?php echo htmlspecialchars($lesson['lesson_number']); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="title">Title *</label>
            <input type="text" class="form-control" id="title" name="title" 
                   value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($lesson['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label" for="quarter">Quarter *</label>
                <select class="form-control" id="quarter" name="quarter" required>
                    <option value="1" <?php echo $lesson['quarter'] == 1 ? 'selected' : ''; ?>>Quarter 1</option>
                    <option value="2" <?php echo $lesson['quarter'] == 2 ? 'selected' : ''; ?>>Quarter 2</option>
                    <option value="3" <?php echo $lesson['quarter'] == 3 ? 'selected' : ''; ?>>Quarter 3</option>
                    <option value="4" <?php echo $lesson['quarter'] == 4 ? 'selected' : ''; ?>>Quarter 4</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="level">Level *</label>
                <input type="number" class="form-control" id="level" name="level" 
                       value="<?php echo $lesson['level']; ?>" min="1" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="order_index">Order Index</label>
            <input type="number" class="form-control" id="order_index" name="order_index" 
                   value="<?php echo $lesson['order_index']; ?>" min="0">
        </div>
        
        <div class="form-group">
            <label class="form-label" for="video_url">Video URL</label>
            <input type="url" class="form-control" id="video_url" name="video_url" 
                   value="<?php echo htmlspecialchars($lesson['video_url'] ?? ''); ?>">
            <?php if ($lesson['video_file']): ?>
                <small>Current file: <?php echo htmlspecialchars($lesson['video_file']); ?></small>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="video_file">Or Upload New Video File (MP4)</label>
            <input type="file" class="form-control" id="video_file" name="video_file" accept="video/mp4">
        </div>
        
        <div class="form-group">
            <label class="form-label" for="material_file">Upload New Material File (PDF, DOC, DOCX)</label>
            <input type="file" class="form-control" id="material_file" name="material_file" accept="application/pdf,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
            <?php if ($lesson['material_file']): ?>
                <small>Current file: <?php echo htmlspecialchars($lesson['material_file']); ?></small>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Lesson
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
