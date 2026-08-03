<?php
require_once '../config/config.php';
requireRole(['student']);

$quizId = $_GET['quiz_id'] ?? 0;
$lessonId = $_GET['lesson_id'] ?? 0;

if (!$quizId || !$lessonId) {
    header('Location: lessons.php');
    exit();
}

$conn = getDBConnection();
$studentId = getCurrentUserId();

// Get quiz details
$stmt = $conn->prepare("
    SELECT q.*, l.title as lesson_title, l.lesson_number, l.pace_number, s.name as subject_name
    FROM quizzes q
    INNER JOIN lessons l ON q.lesson_id = l.id
    INNER JOIN subjects s ON l.subject_id = s.id
    WHERE q.id = ? AND l.id = ?
");
$stmt->bind_param("ii", $quizId, $lessonId);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    header('Location: lessons.php');
    exit();
}

// Check attempts and best score
$stmt = $conn->prepare("
    SELECT COUNT(*) as attempt_count, 
           MAX(percentage) as best_percentage,
           MAX(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as has_passed
    FROM lesson_scores 
    WHERE student_id = ? AND quiz_id = ?
");
$stmt->bind_param("ii", $studentId, $quizId);
$stmt->execute();
$attemptInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

$attemptCount = $attemptInfo['attempt_count'] ?? 0;
$bestPercentage = $attemptInfo['best_percentage'] ?? 0;
$hasPassed = $attemptInfo['has_passed'] ?? 0;
$maxAttempts = 3;
$remainingAttempts = $maxAttempts - $attemptCount;

// If already passed, redirect to results
if ($hasPassed) {
    header('Location: quiz-result.php?lesson_id=' . $lessonId);
    exit();
}

// If exceeded max attempts, redirect to results
if ($attemptCount >= $maxAttempts) {
    header('Location: quiz-result.php?lesson_id=' . $lessonId);
    exit();
}

// Get questions
$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index, id");
$stmt->bind_param("i", $quizId);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);

$pageTitle = 'Take Quiz: ' . $quiz['title'];
$additionalScripts = ['assets/js/quiz.js'];
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h1>
        <?php if ($quiz['time_limit'] > 0): ?>
            <div id="quiz-timer" style="font-size: 1.5rem; font-weight: bold; color: var(--warning-color);">
                <?php echo $quiz['time_limit']; ?>:00
            </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-bottom: 1.5rem;">
        <span class="badge badge-info"><?php echo htmlspecialchars($quiz['subject_name']); ?></span>
        <span class="badge badge-info"><?php echo htmlspecialchars($quiz['pace_number'] ?? $quiz['lesson_number']); ?></span>
        <span class="badge badge-warning">Passing Score: <?php echo number_format($quiz['passing_score'], 0); ?>%</span>
        <span class="badge badge-info">Total Questions: <?php echo count($questions); ?></span>
    </div>
    
    <form id="quiz-form">
        <?php foreach ($questions as $index => $question): ?>
            <div class="question-card">
                <div class="question-text">
                    <strong>Question <?php echo $index + 1; ?>:</strong> <?php echo htmlspecialchars($question['question']); ?>
                    <span style="float: right; color: #666; font-size: 0.9rem;"><?php echo $question['points']; ?> points</span>
                </div>
                
                <?php if ($question['question_type'] === 'true_false'): ?>
                    <ul class="options-list">
                        <li class="option-item">
                            <label>
                                <input type="radio" name="question_<?php echo $question['id']; ?>" value="True" required>
                                True
                            </label>
                        </li>
                        <li class="option-item">
                            <label>
                                <input type="radio" name="question_<?php echo $question['id']; ?>" value="False" required>
                                False
                            </label>
                        </li>
                    </ul>
                <?php else: ?>
                    <ul class="options-list">
                        <?php if ($question['option_a']): ?>
                            <li class="option-item">
                                <label>
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="A" required>
                                    A. <?php echo htmlspecialchars($question['option_a']); ?>
                                </label>
                            </li>
                        <?php endif; ?>
                        <?php if ($question['option_b']): ?>
                            <li class="option-item">
                                <label>
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="B" required>
                                    B. <?php echo htmlspecialchars($question['option_b']); ?>
                                </label>
                            </li>
                        <?php endif; ?>
                        <?php if ($question['option_c']): ?>
                            <li class="option-item">
                                <label>
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="C" required>
                                    C. <?php echo htmlspecialchars($question['option_c']); ?>
                                </label>
                            </li>
                        <?php endif; ?>
                        <?php if ($question['option_d']): ?>
                            <li class="option-item">
                                <label>
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="D" required>
                                    D. <?php echo htmlspecialchars($question['option_d']); ?>
                                </label>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #ddd;">
            <button type="button" onclick="submitQuiz(<?php echo $quizId; ?>, <?php echo $lessonId; ?>)" class="btn btn-success btn-lg">
                <i class="fas fa-paper-plane"></i> Submit Quiz
            </button>
            <a href="lesson.php?id=<?php echo $lessonId; ?>" class="btn btn-secondary btn-lg">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
// Start timer if time limit exists
<?php if ($quiz['time_limit'] > 0): ?>
    startQuizTimer(<?php echo $quiz['time_limit']; ?>, function() {
        if (confirm('Time is up! Do you want to submit your quiz now?')) {
            submitQuiz(<?php echo $quizId; ?>, <?php echo $lessonId; ?>);
        }
    });
<?php endif; ?>

// Load saved progress
loadQuizProgress(<?php echo $quizId; ?>);

// Auto-save on answer change
document.querySelectorAll('input[type="radio"]').forEach(input => {
    input.addEventListener('change', function() {
        const formData = new FormData(document.getElementById('quiz-form'));
        const answers = {};
        formData.forEach((value, key) => {
            if (key.startsWith('question_')) {
                answers[key] = value;
            }
        });
        autoSaveQuiz(<?php echo $quizId; ?>, answers);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
