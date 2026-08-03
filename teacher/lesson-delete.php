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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Delete associated files
    if ($lesson['video_file'] && file_exists(UPLOAD_PATH_VIDEOS . $lesson['video_file'])) {
        unlink(UPLOAD_PATH_VIDEOS . $lesson['video_file']);
    }
    if ($lesson['material_file'] && file_exists(UPLOAD_PATH_MATERIALS . $lesson['material_file'])) {
        unlink(UPLOAD_PATH_MATERIALS . $lesson['material_file']);
    }
    
    // Delete lesson (cascade will handle related records)
    $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $lessonId);
    $stmt->execute();
    $stmt->close();
    
    closeDBConnection($conn);
    header('Location: lessons.php?subject_id=' . $lesson['subject_id']);
    exit();
}

closeDBConnection($conn);

$pageTitle = 'Delete Lesson';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Delete Lesson</h1>
        <a href="lessons.php?subject_id=<?php echo $lesson['subject_id']; ?>" class="btn btn-secondary">Cancel</a>
    </div>
    
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Warning!</strong> Are you sure you want to delete this lesson? This action cannot be undone.
    </div>
    
    <div style="padding: 1.5rem; background: #f8f9fa; border-radius: 5px; margin-bottom: 1.5rem;">
        <p><strong>Lesson Number:</strong> <?php echo htmlspecialchars($lesson['lesson_number']); ?></p>
        <p><strong>Title:</strong> <?php echo htmlspecialchars($lesson['title']); ?></p>
        <p><strong>Subject:</strong> <?php echo htmlspecialchars($lesson['subject_name']); ?></p>
        <p><strong>Quarter:</strong> <?php echo $lesson['quarter']; ?></p>
    </div>
    
    <form method="POST">
        <input type="hidden" name="confirm" value="1">
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash"></i> Confirm Delete
        </button>
        <a href="lessons.php?subject_id=<?php echo $lesson['subject_id']; ?>" class="btn btn-secondary">
            Cancel
        </a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
