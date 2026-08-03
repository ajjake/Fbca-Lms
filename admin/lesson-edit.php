<?php
require_once '../config/config.php';
requireRole(['admin']);

$lessonId = $_GET['id'] ?? 0;
if (!$lessonId) {
    header('Location: lessons.php');
    exit();
}

$conn = getDBConnection();

// Get lesson data
$stmt = $conn->prepare("
    SELECT l.*, s.name as subject_name, s.code as subject_code
    FROM lessons l
    INNER JOIN subjects s ON l.subject_id = s.id
    WHERE l.id = ?
");
$stmt->bind_param("i", $lessonId);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lesson) {
    header('Location: lessons.php');
    exit();
}

// Get all subjects
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get quiz if exists
$quiz = null;
$quizQuestions = [];
$quizStmt = $conn->prepare("SELECT * FROM quizzes WHERE lesson_id = ?");
$quizStmt->bind_param("i", $lessonId);
$quizStmt->execute();
$quiz = $quizStmt->get_result()->fetch_assoc();
$quizStmt->close();

if ($quiz) {
    $questionsStmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index");
    $questionsStmt->bind_param("i", $quiz['id']);
    $questionsStmt->execute();
    $quizQuestions = $questionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $questionsStmt->close();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lesson data
    $subjectId = $_POST['subject_id'] ?? $lesson['subject_id'];
    $lessonNumber = $_POST['lesson_number'] ?? '';
    $paceNumber = $_POST['lesson_number'] ?? ''; // Use same as lesson_number
    $paceType = $_POST['pace_type'] ?? ($lesson['pace_type'] ?? 'lesson');
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $content = $_POST['content'] ?? '';
    $quarter = $_POST['quarter'] ?? 1;
    $level = $_POST['level'] ?? 1;
    $videoUrl = $_POST['video_url'] ?? '';
    $orderIndex = $_POST['order_index'] ?? 0;
    
    // Validate
    if (empty($lessonNumber) || empty($title)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if lesson number already exists (excluding current lesson)
        $checkStmt = $conn->prepare("SELECT id FROM lessons WHERE lesson_number = ? AND id != ?");
        $checkStmt->bind_param("si", $lessonNumber, $lessonId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = 'Lesson number already exists.';
            $checkStmt->close();
        } else {
            $checkStmt->close();
            // Handle file uploads
            $videoFile = $lesson['video_file'];
            $materialFile = $lesson['material_file'];
            $imageFile = $lesson['image_file'];
            
            // Video file
            if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                if ($videoFile && file_exists(UPLOAD_PATH_VIDEOS . $videoFile)) {
                    unlink(UPLOAD_PATH_VIDEOS . $videoFile);
                }
                $uploadDir = UPLOAD_PATH_VIDEOS;
                $fileName = time() . '_' . basename($_FILES['video_file']['name']);
                if (move_uploaded_file($_FILES['video_file']['tmp_name'], $uploadDir . $fileName)) {
                    $videoFile = $fileName;
                }
            }
            
            // Material file
            if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                if ($materialFile && file_exists(UPLOAD_PATH_MATERIALS . $materialFile)) {
                    unlink(UPLOAD_PATH_MATERIALS . $materialFile);
                }
                $uploadDir = UPLOAD_PATH_MATERIALS;
                $fileName = time() . '_' . basename($_FILES['material_file']['name']);
                if (move_uploaded_file($_FILES['material_file']['tmp_name'], $uploadDir . $fileName)) {
                    $materialFile = $fileName;
                }
            }
            
            // Image file
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                if ($imageFile && file_exists(UPLOAD_PATH_IMAGES . $imageFile)) {
                    unlink(UPLOAD_PATH_IMAGES . $imageFile);
                }
                $uploadDir = UPLOAD_PATH_IMAGES;
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = $_FILES['image_file']['type'];
                
                if (in_array($fileType, $allowedTypes)) {
                    $fileName = time() . '_' . basename($_FILES['image_file']['name']);
                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $fileName)) {
                        $imageFile = $fileName;
                    }
                } else {
                    $error = 'Invalid image file type. Allowed: JPG, PNG, GIF, WEBP';
                }
            }
            
            // Handle image deletion
            if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
                if ($imageFile && file_exists(UPLOAD_PATH_IMAGES . $imageFile)) {
                    unlink(UPLOAD_PATH_IMAGES . $imageFile);
                }
                $imageFile = '';
            }
            
            if (empty($error)) {
                // Update lesson
                $stmt = $conn->prepare("
                    UPDATE lessons 
                    SET subject_id = ?, lesson_number = ?, pace_number = ?, pace_type = ?, title = ?, description = ?, content = ?, 
                        quarter = ?, level = ?, video_url = ?, video_file = ?, material_file = ?, 
                        image_file = ?, order_index = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("issssssiissssii", $subjectId, $lessonNumber, $paceNumber, $paceType, $title, $description, $content,
                                 $quarter, $level, $videoUrl, $videoFile, $materialFile, $imageFile, 
                                 $orderIndex, $lessonId);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    $message = 'Lesson updated successfully!';
                    // Reload lesson data
                    $reloadStmt = $conn->prepare("
                        SELECT l.*, s.name as subject_name, s.code as subject_code
                        FROM lessons l
                        INNER JOIN subjects s ON l.subject_id = s.id
                        WHERE l.id = ?
                    ");
                    $reloadStmt->bind_param("i", $lessonId);
                    $reloadStmt->execute();
                    $lesson = $reloadStmt->get_result()->fetch_assoc();
                    $reloadStmt->close();
                    
                    // Reload quiz data
                    $quizStmt = $conn->prepare("SELECT * FROM quizzes WHERE lesson_id = ?");
                    $quizStmt->bind_param("i", $lessonId);
                    $quizStmt->execute();
                    $quiz = $quizStmt->get_result()->fetch_assoc();
                    $quizStmt->close();
                    
                    if ($quiz) {
                        $questionsStmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index");
                        $questionsStmt->bind_param("i", $quiz['id']);
                        $questionsStmt->execute();
                        $quizQuestions = $questionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $questionsStmt->close();
                    }
                } else {
                    $error = 'Failed to update lesson: ' . $conn->error;
                    $stmt->close();
                }
            }
        }
    }
}

closeDBConnection($conn);

$pageTitle = 'Edit Lesson';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Edit Lesson</h1>
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
    
    <form method="POST" enctype="multipart/form-data" id="lessonForm">
        <h2 style="margin-top: 2rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;">Lesson Information</h2>
        
        <div class="form-group">
            <label class="form-label" for="subject_id">Subject *</label>
            <select class="form-control" id="subject_id" name="subject_id" required>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?php echo $subject['id']; ?>" <?php echo $lesson['subject_id'] == $subject['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($subject['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label" for="lesson_number">PACE Number *</label>
                <input type="text" class="form-control" id="lesson_number" name="lesson_number" 
                       value="<?php echo htmlspecialchars($lesson['pace_number'] ?? $lesson['lesson_number']); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="pace_type">PACE Type *</label>
                <select class="form-control" id="pace_type" name="pace_type" required>
                    <option value="lesson" <?php echo ($lesson['pace_type'] ?? 'lesson') === 'lesson' ? 'selected' : ''; ?>>Regular PACE (Lesson)</option>
                    <option value="monthly_test" <?php echo ($lesson['pace_type'] ?? '') === 'monthly_test' ? 'selected' : ''; ?>>Monthly Test (after 2 PACEs)</option>
                    <option value="quarter_test" <?php echo ($lesson['pace_type'] ?? '') === 'quarter_test' ? 'selected' : ''; ?>>Quarter Test (after 3 PACEs)</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="title">Title *</label>
            <input type="text" class="form-control" id="title" name="title" 
                   value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="description">Short Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($lesson['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="content">Content (Text/HTML)</label>
            <textarea class="form-control" id="content" name="content" rows="10"><?php echo htmlspecialchars($lesson['content'] ?? ''); ?></textarea>
            <small>You can use HTML tags for formatting (e.g., &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;, &lt;li&gt;)</small>
        </div>
        
        <div class="grid grid-3">
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
                       value="<?php echo htmlspecialchars($lesson['level']); ?>" min="1" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="order_index">Order Index</label>
                <input type="number" class="form-control" id="order_index" name="order_index" 
                       value="<?php echo htmlspecialchars($lesson['order_index']); ?>" min="0">
            </div>
        </div>
        
        <h2 style="margin-top: 2rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;">Media Files</h2>
        
        <div class="form-group">
            <label class="form-label" for="image_file">Image/Photo</label>
            <?php if ($lesson['image_file']): ?>
                <div style="margin-bottom: 1rem;">
                    <img src="<?php echo BASE_URL . 'uploads/images/' . htmlspecialchars($lesson['image_file']); ?>" 
                         alt="Current image" style="max-width: 200px; height: auto; border-radius: 5px; border: 1px solid #ddd;">
                    <br>
                    <label style="margin-top: 0.5rem;">
                        <input type="checkbox" name="delete_image" value="1"> Delete current image
                    </label>
                </div>
            <?php endif; ?>
            <input type="file" class="form-control" id="image_file" name="image_file" 
                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
            <small>Supported formats: JPG, PNG, GIF, WEBP</small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="video_url">Video URL (YouTube or other)</label>
            <input type="url" class="form-control" id="video_url" name="video_url" 
                   placeholder="https://www.youtube.com/watch?v=..."
                   value="<?php echo htmlspecialchars($lesson['video_url'] ?? ''); ?>">
            <?php if ($lesson['video_file']): ?>
                <small>Current video file: <?php echo htmlspecialchars($lesson['video_file']); ?></small>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="video_file">Or Upload New Video File (MP4)</label>
            <input type="file" class="form-control" id="video_file" name="video_file" accept="video/mp4">
        </div>
        
        <div class="form-group">
            <label class="form-label" for="material_file">Material File (PDF, DOC, DOCX)</label>
            <?php if ($lesson['material_file']): ?>
                <div style="margin-bottom: 0.5rem;">
                    <small>Current file: <?php echo htmlspecialchars($lesson['material_file']); ?></small>
                </div>
            <?php endif; ?>
            <input type="file" class="form-control" id="material_file" name="material_file" 
                   accept="application/pdf,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        </div>
        
        <div style="margin-top: 2rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Lesson
            </button>
        </div>
    </form>
    
    <?php if ($quiz): ?>
        <div style="margin-top: 3rem; padding-top: 2rem; border-top: 2px solid #ddd;">
            <h2>Quiz Information</h2>
            <div class="alert alert-info">
                <p><strong>Quiz Title:</strong> <?php echo htmlspecialchars($quiz['title']); ?></p>
                <p><strong>Passing Score:</strong> <?php echo $quiz['passing_score']; ?>%</p>
                <p><strong>Time Limit:</strong> <?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' minutes' : 'No limit'; ?></p>
                <p><strong>Questions:</strong> <?php echo count($quizQuestions); ?></p>
                <a href="quiz-edit.php?quiz_id=<?php echo $quiz['id']; ?>&lesson_id=<?php echo $lessonId; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Quiz
                </a>
            </div>
        </div>
    <?php else: ?>
        <div style="margin-top: 3rem; padding-top: 2rem; border-top: 2px solid #ddd;">
            <h2>Quiz</h2>
            <div class="alert alert-info">
                <p>No quiz exists for this lesson.</p>
                <a href="quiz-add.php?lesson_id=<?php echo $lessonId; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Quiz
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
