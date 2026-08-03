<?php
require_once '../config/config.php';
requireRole(['teacher']);

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

// Get assigned levels for this teacher
$stmt = $conn->prepare("SELECT level FROM teacher_levels WHERE teacher_id = ?");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$assignedLvRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$assignedLevels = array_column($assignedLvRows, 'level');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjectId = $_POST['subject_id'] ?? 0;
    $lessonNumber = $_POST['lesson_number'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $quarter = $_POST['quarter'] ?? 1;
    $level = $_POST['level'] ?? 0;
    $videoUrl = $_POST['video_url'] ?? '';
    $orderIndex = $_POST['order_index'] ?? 0;
    
    // Validate
    if (empty($subjectId) || empty($lessonNumber) || empty($title) || empty($level)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if lesson number already exists
        $stmt = $conn->prepare("SELECT id FROM lessons WHERE lesson_number = ?");
        $stmt->bind_param("s", $lessonNumber);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Lesson number already exists.';
        } else {
            // Handle file uploads
            $videoFile = '';
            $materialFile = '';
            
            if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = UPLOAD_PATH_VIDEOS;
                $fileName = time() . '_' . basename($_FILES['video_file']['name']);
                if (move_uploaded_file($_FILES['video_file']['tmp_name'], $uploadDir . $fileName)) {
                    $videoFile = $fileName;
                }
            }
            
            if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = UPLOAD_PATH_MATERIALS;
                $fileName = time() . '_' . basename($_FILES['material_file']['name']);
                if (move_uploaded_file($_FILES['material_file']['tmp_name'], $uploadDir . $fileName)) {
                    $materialFile = $fileName;
                }
            }
            
            // Insert lesson
            // Ensure teacher is allowed to create lesson for this level
            $allowed = false;
            if (!empty($assignedLevels) && in_array((int)$level, $assignedLevels)) {
                $allowed = true;
            }
            if (empty($assignedLevels)) {
                $error = 'You are not assigned any levels. Contact admin.';
            } elseif (!$allowed) {
                $error = 'You are not permitted to create lessons for Level ' . htmlspecialchars($level);
            }

            if (empty($error)) {
                $stmt = $conn->prepare(" 
                INSERT INTO lessons (subject_id, lesson_number, title, description, quarter, level, video_url, video_file, material_file, order_index)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssiisssi", $subjectId, $lessonNumber, $title, $description, $quarter, $level, $videoUrl, $videoFile, $materialFile, $orderIndex);
            
            if ($stmt->execute()) {
                $message = 'Lesson added successfully!';
                // Reset form
                $selectedSubject = $subjectId;
            } else {
                $error = 'Failed to add lesson.';
            }
            $stmt->close();
            }
        }
    }
}

closeDBConnection($conn);

$pageTitle = 'Add Lesson';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Add New Lesson</h1>
        <a href="lessons.php" class="btn btn-secondary">Back to Lessons</a>
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
                <?php foreach ($assignedSubjects as $subject): ?>
                    <option value="<?php echo $subject['id']; ?>" <?php echo $selectedSubject == $subject['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($subject['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="lesson_number">Lesson Number * (e.g., English 1001)</label>
            <input type="text" class="form-control" id="lesson_number" name="lesson_number" required>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="title">Title *</label>
            <input type="text" class="form-control" id="title" name="title" required>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"></textarea>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label" for="quarter">Quarter *</label>
                <select class="form-control" id="quarter" name="quarter" required>
                    <option value="1">Quarter 1</option>
                    <option value="2">Quarter 2</option>
                    <option value="3">Quarter 3</option>
                    <option value="4">Quarter 4</option>
                </select>
            </div>
        
            <div class="form-group">
                <label class="form-label" for="level">Level *</label>
                <?php if (empty($assignedLevels)): ?>
                    <div class="alert alert-warning">You have not been assigned any levels. Contact an admin to assign levels before creating lessons.</div>
                <?php else: ?>
                    <select class="form-control" id="level" name="level" required>
                        <option value="">-- Select Level --</option>
                        <?php foreach ($assignedLevels as $lv): ?>
                            <option value="<?php echo (int)$lv; ?>"><?php echo 'Level ' . (int)$lv; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="order_index">Order Index</label>
            <input type="number" class="form-control" id="order_index" name="order_index" value="0" min="0">
            <small>Used to order lessons within the same quarter</small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="video_url">Video URL (YouTube or other)</label>
            <input type="url" class="form-control" id="video_url" name="video_url" placeholder="https://www.youtube.com/watch?v=...">
        </div>
        
        <div class="form-group">
            <label class="form-label" for="video_file">Or Upload Video File (MP4)</label>
            <input type="file" class="form-control" id="video_file" name="video_file" accept="video/mp4">
        </div>
        
        <div class="form-group">
            <label class="form-label" for="material_file">Material File (PDF, DOC, DOCX)</label>
            <input type="file" class="form-control" id="material_file" name="material_file" accept="application/pdf,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Add Lesson
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
