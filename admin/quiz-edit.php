<?php
require_once '../config/config.php';
requireRole(['admin']);

$quizId = $_GET['quiz_id'] ?? $_GET['id'] ?? 0;
$lessonId = $_GET['lesson_id'] ?? 0;

if (!$quizId) {
    header('Location: lessons.php');
    exit();
}

$conn = getDBConnection();

// Get quiz details (no teacher restriction for admin)
$stmt = $conn->prepare("
    SELECT q.*, l.title as lesson_title, l.lesson_number, l.subject_id, l.id as lesson_id, s.name as subject_name
    FROM quizzes q
    INNER JOIN lessons l ON q.lesson_id = l.id
    INNER JOIN subjects s ON l.subject_id = s.id
    WHERE q.id = ?
");
$stmt->bind_param("i", $quizId);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    header('Location: lessons.php');
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
        $stmt = $conn->prepare("
            UPDATE quizzes SET title = ?, passing_score = ?, time_limit = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sdii", $title, $passingScore, $timeLimit, $quizId);
        
        if ($stmt->execute()) {
            $stmt->close();
            $message = 'Quiz updated successfully!';
            // Reload quiz data
            $reloadStmt = $conn->prepare("
                SELECT q.*, l.title as lesson_title, l.lesson_number, l.subject_id, l.id as lesson_id, s.name as subject_name
                FROM quizzes q
                INNER JOIN lessons l ON q.lesson_id = l.id
                INNER JOIN subjects s ON l.subject_id = s.id
                WHERE q.id = ?
            ");
            $reloadStmt->bind_param("i", $quizId);
            $reloadStmt->execute();
            $quiz = $reloadStmt->get_result()->fetch_assoc();
            $reloadStmt->close();
        } else {
            $error = 'Failed to update quiz: ' . $conn->error;
            $stmt->close();
        }
    }
}

closeDBConnection($conn);

$pageTitle = 'Edit Quiz';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Edit Quiz</h1>
        <a href="lesson-edit.php?id=<?php echo $quiz['lesson_id']; ?>" class="btn btn-secondary">Back to Lesson</a>
    </div>
    
    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
        <p><strong>Lesson:</strong> <?php echo htmlspecialchars($quiz['lesson_number']); ?> - <?php echo htmlspecialchars($quiz['lesson_title']); ?></p>
        <p><strong>Subject:</strong> <?php echo htmlspecialchars($quiz['subject_name']); ?></p>
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
    
    <form method="POST">
        <div class="form-group">
            <label class="form-label" for="title">Quiz Title *</label>
            <input type="text" class="form-control" id="title" name="title" 
                   value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label" for="passing_score">Passing Score (%) *</label>
                <input type="number" class="form-control" id="passing_score" name="passing_score" 
                       value="<?php echo $quiz['passing_score']; ?>" min="0" max="100" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="time_limit">Time Limit (minutes)</label>
                <input type="number" class="form-control" id="time_limit" name="time_limit" 
                       value="<?php echo $quiz['time_limit']; ?>" min="0">
                <small>Enter 0 for no time limit</small>
            </div>
        </div>
        
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Quiz
            </button>
            <a href="quiz-questions.php?quiz_id=<?php echo $quizId; ?>&lesson_id=<?php echo $quiz['lesson_id']; ?>" class="btn btn-success">
                <i class="fas fa-question-circle"></i> Manage Questions
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
