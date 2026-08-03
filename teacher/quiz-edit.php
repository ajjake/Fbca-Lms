<?php
require_once '../config/config.php';
requireRole(['teacher']);

$quizId = $_GET['id'] ?? 0;
if (!$quizId) {
    header('Location: quizzes.php');
    exit();
}

$conn = getDBConnection();
$teacherId = getCurrentUserId();

// Verify quiz belongs to teacher's subject
$stmt = $conn->prepare("
    SELECT q.*, l.title as lesson_title, l.lesson_number, l.subject_id, l.id as lesson_id, s.name as subject_name
    FROM quizzes q
    INNER JOIN lessons l ON q.lesson_id = l.id
    INNER JOIN subjects s ON l.subject_id = s.id
    INNER JOIN teacher_subjects ts ON l.subject_id = ts.subject_id
    WHERE q.id = ? AND ts.teacher_id = ?
");
$stmt->bind_param("ii", $quizId, $teacherId);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
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
        $stmt = $conn->prepare("
            UPDATE quizzes SET title = ?, passing_score = ?, time_limit = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sdii", $title, $passingScore, $timeLimit, $quizId);
        
        if ($stmt->execute()) {
            $message = 'Quiz updated successfully!';
            // Reload quiz data
            $stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
            $stmt->bind_param("i", $quizId);
            $stmt->execute();
            $quiz = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $error = 'Failed to update quiz.';
        }
        $stmt->close();
    }
}

closeDBConnection($conn);

$pageTitle = 'Edit Quiz';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Edit Quiz</h1>
        <a href="quizzes.php?subject_id=<?php echo $quiz['subject_id']; ?>&lesson_id=<?php echo $quiz['lesson_id']; ?>" class="btn btn-secondary">Back</a>
    </div>
    
    <div style="margin-bottom: 1.5rem;">
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
                       value="<?php echo $quiz['passing_score']; ?>" min="0" max="100" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="time_limit">Time Limit (minutes)</label>
                <input type="number" class="form-control" id="time_limit" name="time_limit" 
                       value="<?php echo $quiz['time_limit']; ?>" min="0">
                <small>Enter 0 for no time limit</small>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Quiz
        </button>
        <a href="quiz-questions.php?quiz_id=<?php echo $quizId; ?>" class="btn btn-success">
            <i class="fas fa-question-circle"></i> Manage Questions
        </a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
