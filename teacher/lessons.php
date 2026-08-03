<?php
require_once '../config/config.php';
requireRole(['teacher']);

$pageTitle = 'Manage Lessons';
include '../includes/header.php';

$conn = getDBConnection();
$teacherId = getCurrentUserId();
$selectedSubject = $_GET['subject_id'] ?? 0;

// Get assigned subjects
$stmt = $conn->prepare("
    SELECT s.* FROM subjects s
    INNER JOIN teacher_subjects ts ON s.id = ts.subject_id
    WHERE ts.teacher_id = ?
    ORDER BY s.name
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$assignedSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get lessons for selected subject
$lessons = [];
if ($selectedSubject) {
    $stmt = $conn->prepare("
        SELECT l.*, s.name as subject_name
        FROM lessons l
        INNER JOIN subjects s ON l.subject_id = s.id
        WHERE l.subject_id = ?
        ORDER BY l.quarter, l.order_index
    ");
    $stmt->bind_param("i", $selectedSubject);
    $stmt->execute();
    $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

closeDBConnection($conn);
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Manage Lessons</h1>
        <a href="lesson-add.php" class="btn btn-primary">Add New Lesson</a>
    </div>
    
    <div class="form-group">
        <label class="form-label">Select Subject</label>
        <select class="form-control" onchange="window.location.href='?subject_id=' + this.value">
            <option value="0">-- Select Subject --</option>
            <?php foreach ($assignedSubjects as $subject): ?>
                <option value="<?php echo $subject['id']; ?>" <?php echo $selectedSubject == $subject['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($subject['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <?php if ($selectedSubject && !empty($lessons)): ?>
        <div class="table-container" style="margin-top: 2rem;">
            <table>
                <thead>
                    <tr>
                        <th>Lesson Number</th>
                        <th>Title</th>
                        <th>Quarter</th>
                        <th>Level</th>
                        <th>Video</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessons as $lesson): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lesson['lesson_number']); ?></td>
                            <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                            <td>Q<?php echo $lesson['quarter']; ?></td>
                            <td>Level <?php echo $lesson['level']; ?></td>
                            <td>
                                <?php if ($lesson['video_url'] || $lesson['video_file']): ?>
                                    <span class="badge badge-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="lesson-edit.php?id=<?php echo $lesson['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="lesson-delete.php?id=<?php echo $lesson['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this lesson?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($selectedSubject): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No lessons found for this subject. <a href="lesson-add.php?subject_id=<?php echo $selectedSubject; ?>">Add a lesson</a>.
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
