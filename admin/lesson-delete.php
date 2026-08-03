<?php
require_once '../config/config.php';
requireRole(['admin']);

$lessonId = $_GET['id'] ?? 0;
if (!$lessonId) {
    header('Location: lessons.php');
    exit();
}

$conn = getDBConnection();

// Get lesson info
$stmt = $conn->prepare("SELECT l.*, s.name as subject_name FROM lessons l INNER JOIN subjects s ON l.subject_id = s.id WHERE l.id = ?");
$stmt->bind_param("i", $lessonId);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lesson) {
    header('Location: lessons.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Delete associated files
    if ($lesson['video_file'] && file_exists(UPLOAD_PATH_VIDEOS . $lesson['video_file'])) {
        unlink(UPLOAD_PATH_VIDEOS . $lesson['video_file']);
    }
    if ($lesson['material_file'] && file_exists(UPLOAD_PATH_MATERIALS . $lesson['material_file'])) {
        unlink(UPLOAD_PATH_MATERIALS . $lesson['material_file']);
    }
    if ($lesson['image_file'] && file_exists(UPLOAD_PATH_IMAGES . $lesson['image_file'])) {
        unlink(UPLOAD_PATH_IMAGES . $lesson['image_file']);
    }
    
    // Delete lesson (cascade will delete quizzes, questions, progress, etc.)
    $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $lessonId);
    
    if ($stmt->execute()) {
        closeDBConnection($conn);
        header('Location: lessons.php?msg=' . urlencode('Lesson deleted successfully.'));
        exit();
    } else {
        $error = 'Failed to delete lesson: ' . $conn->error;
    }
    $stmt->close();
}

closeDBConnection($conn);

$pageTitle = 'Delete Lesson';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Delete Lesson</h1>
        <a href="lessons.php" class="btn btn-secondary">Cancel</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Warning!</strong> You are about to delete the following lesson. This action cannot be undone.
    </div>
    
    <div style="padding: 1.5rem;">
        <h3><?php echo htmlspecialchars($lesson['title']); ?></h3>
        <p><strong>Lesson Number:</strong> <?php echo htmlspecialchars($lesson['lesson_number']); ?></p>
        <p><strong>Subject:</strong> <?php echo htmlspecialchars($lesson['subject_name']); ?></p>
        <p><strong>Quarter:</strong> <?php echo $lesson['quarter']; ?></p>
        <p><strong>Level:</strong> <?php echo $lesson['level']; ?></p>
    </div>
    
    <div class="alert alert-danger">
        <strong>This will also delete:</strong>
        <ul>
            <li>All associated quizzes and questions</li>
            <li>All student progress records for this lesson</li>
            <li>All quiz scores for this lesson</li>
            <li>All uploaded files (videos, materials, images)</li>
        </ul>
    </div>
    
    <form method="POST" style="margin-top: 2rem;">
        <input type="hidden" name="confirm_delete" value="1">
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash"></i> Yes, Delete This Lesson
        </button>
        <a href="lessons.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
