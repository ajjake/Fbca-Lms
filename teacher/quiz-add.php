<?php
require_once '../config/config.php';
requireRole(['teacher']);

$lessonId = $_GET['lesson_id'] ?? 0;
if (!$lessonId) {
    header('Location: quizzes.php');
    exit();
}

$conn = getDBConnection();
$teacherId = getCurrentUserId();

// Verify lesson belongs to teacher's subject
$stmt = $conn->prepare("
    SELECT l.*, s.name as subject_name, s.id as subject_id
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
    header('Location: quizzes.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $passingScore = $_POST['passing_score'] ?? 75;
    $timeLimit = $_POST['time_limit'] ?? 0;
    
    if (empty($title)) {
        $error = 'Please enter a quiz title.';
    } else {
        // Check if quiz already exists for this lesson
        $stmt = $conn->prepare("SELECT id FROM quizzes WHERE lesson_id = ?");
        $stmt->bind_param("i", $lessonId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'A quiz already exists for this lesson.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO quizzes (lesson_id, title, passing_score, time_limit)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isdi", $lessonId, $title, $passingScore, $timeLimit);
            
            if ($stmt->execute()) {
                $quizId = $conn->insert_id;
                header('Location: quiz-questions.php?quiz_id=' . $quizId);
                exit();
            } else {
                $error = 'Failed to create quiz.';
            }
            $stmt->close();
        }
    }
}

closeDBConnection($conn);

$pageTitle = 'Add Quiz';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Add Quiz</h1>
        <a href="quizzes.php?subject_id=<?php echo $lesson['subject_id']; ?>&lesson_id=<?php echo $lessonId; ?>" class="btn btn-secondary">Back</a>
    </div>
    
    <div style="margin-bottom: 1.5rem;">
        <p><strong>Lesson:</strong> <?php echo htmlspecialchars($lesson['lesson_number']); ?> - <?php echo htmlspecialchars($lesson['title']); ?></p>
        <p><strong>Subject:</strong> <?php echo htmlspecialchars($lesson['subject_name']); ?></p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label class="form-label" for="title">Quiz Title *</label>
            <input type="text" class="form-control" id="title" name="title" required>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label" for="passing_score">Passing Score (%) *</label>
                <input type="number" class="form-control" id="passing_score" name="passing_score" value="75" min="0" max="100" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="time_limit">Time Limit (minutes)</label>
                <input type="number" class="form-control" id="time_limit" name="time_limit" value="0" min="0">
                <small>Enter 0 for no time limit</small>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Create Quiz
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
