<?php
use App\Application\StudentLessonService;

require_once '../config/config.php';
requireRole(['student']);

$quizId = (int) ($_GET['quiz_id'] ?? 0);
$lessonId = (int) ($_GET['lesson_id'] ?? 0);

if (!$quizId || !$lessonId) {
    header('Location: lessons.php');
    exit();
}

$studentId = getCurrentUserId();
$service = new StudentLessonService();
$quiz = $service->getQuizDetails($quizId, $lessonId);

if (!$quiz) {
    header('Location: lessons.php');
    exit();
}

$attemptInfo = $service->getAttemptInfo($studentId, $quizId);
$attemptCount = $attemptInfo['total_attempts'];
$bestPercentage = $attemptInfo['best_percentage'];
$hasPassed = $attemptInfo['has_passed'];
$maxAttempts = 3;
$remainingAttempts = max(0, $maxAttempts - $attemptCount);

if ($hasPassed || $attemptCount >= $maxAttempts) {
    header('Location: quiz-result.php?lesson_id=' . $lessonId);
    exit();
}

$questions = $service->getQuizQuestions($quizId);

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
