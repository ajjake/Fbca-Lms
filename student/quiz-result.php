<?php
use App\Application\StudentLessonService;

require_once '../config/config.php';
requireRole(['student']);

$lessonId = (int) ($_GET['lesson_id'] ?? 0);
$scoreId = isset($_GET['score_id']) ? (int) $_GET['score_id'] : null;

if (!$lessonId) {
    header('Location: lessons.php');
    exit();
}

$studentId = getCurrentUserId();
$service = new StudentLessonService();
$score = $service->getScoreDetails($studentId, $lessonId, $scoreId);

if (!$score) {
    header('Location: lessons.php');
    exit();
}

$attemptInfo = $service->getAttemptInfo($studentId, $score['quiz_id']);
$totalAttempts = $attemptInfo['total_attempts'];
$bestPercentage = $attemptInfo['best_percentage'];
$hasPassed = $attemptInfo['has_passed'];
$maxAttempts = 3;
$remainingAttempts = max(0, $maxAttempts - $totalAttempts);
$attemptNumber = $score['attempt_number'] ?? $totalAttempts;

$quiz = $service->getQuizDetails($score['quiz_id'], $lessonId);
$quizInfo = $quiz ?: ['passing_score' => 0];
$nextLesson = $service->getNextLessonUnlockInfo($lessonId, $studentId);
$allAttempts = $service->getAllQuizAttempts($studentId, $score['quiz_id']);

$pageTitle = 'Quiz Results';
include '../includes/header.php';
?>


<div class="card">
    <div class="card-header">
        <h1 class="card-title">Quiz Results</h1>
        <a href="lesson.php?id=<?php echo $lessonId; ?>" class="btn btn-secondary">Back to Lesson</a>
    </div>
    
    <div style="text-align: center; padding: 2rem; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border-radius: 10px; color: white; margin-bottom: 2rem;">
        <h2 style="margin-bottom: 1rem;"><?php echo htmlspecialchars($score['quiz_title']); ?></h2>
        <div style="font-size: 4rem; font-weight: bold; margin: 1rem 0;">
            <?php echo number_format($score['percentage'], 1); ?>%
        </div>
        <div style="font-size: 1.2rem; margin-bottom: 1rem;">
            Score: <?php echo number_format($score['score'], 2); ?> / <?php echo number_format($score['total_points'], 2); ?> points
        </div>
        <?php if ($score['passed']): ?>
            <span class="badge" style="background: rgba(255,255,255,0.3); font-size: 1.2rem; padding: 0.5rem 1.5rem;">
                <i class="fas fa-check-circle"></i> PASSED
            </span>
        <?php else: ?>
            <span class="badge" style="background: rgba(255,255,255,0.3); font-size: 1.2rem; padding: 0.5rem 1.5rem;">
                <i class="fas fa-times-circle"></i> FAILED
            </span>
        <?php endif; ?>
    </div>
    
    <div class="progress" style="margin-bottom: 2rem;">
        <div class="progress-bar" style="width: <?php echo $score['percentage']; ?>%;">
            <?php echo number_format($score['percentage'], 1); ?>%
        </div>
    </div>
    
    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem;">
        <h3>Lesson Information</h3>
        <p><strong>Subject:</strong> <?php echo htmlspecialchars($score['subject_name']); ?></p>
        <p><strong>PACE:</strong> <?php echo htmlspecialchars($score['pace_number'] ?? $score['lesson_number']); ?> - <?php echo htmlspecialchars($score['lesson_title']); ?></p>
        <p><strong>Date Taken:</strong> <?php echo date('F j, Y g:i A', strtotime($score['taken_at'])); ?></p>
    </div>
    
    <?php
    // These values are already loaded via service layer
    $attemptNumber = $score['attempt_number'] ?? $totalAttempts;
    ?>
    
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
        <p><strong>Attempt:</strong> <?php echo $attemptNumber; ?> of <?php echo $maxAttempts; ?></p>
        <p><strong>Best Score:</strong> <?php echo number_format($bestPercentage, 2); ?>%</p>
        <?php if ($remainingAttempts > 0 && !$hasPassed): ?>
            <p><strong>Remaining Attempts:</strong> <?php echo $remainingAttempts; ?></p>
        <?php endif; ?>
    </div>
    
    <?php if ($score['passed'] || $hasPassed): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <strong>Congratulations!</strong> You have passed this quiz. 
            <?php
            if ($nextLesson && ($nextLesson['status'] === 'unlocked' || $nextLesson['status'] === 'in_progress')) {
                echo 'The next PACE "' . htmlspecialchars($nextLesson['pace_number'] ?? $nextLesson['lesson_number']) . '" has been unlocked!';
            } elseif ($nextLesson && ($nextLesson['status'] === 'locked' || $nextLesson['status'] === null)) {
                echo 'Request exam approval to unlock the next lesson.';
            }
            ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>You did not pass this quiz.</strong> You need to score at least <?php echo number_format($quizInfo['passing_score'], 0); ?>% to pass.
            <?php if ($remainingAttempts > 0): ?>
                <p style="margin-top: 1rem;">
                    You have <strong><?php echo $remainingAttempts; ?></strong> retake attempt(s) remaining.
                </p>
                <div style="margin-top: 1rem;">
                    <a href="lesson.php?id=<?php echo $lessonId; ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-book"></i> Review Lesson
                    </a>
                    <a href="take-quiz.php?quiz_id=<?php echo $score['quiz_id']; ?>&lesson_id=<?php echo $lessonId; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-redo"></i> Retake Quiz (Attempt <?php echo $totalAttempts + 1; ?>)
                    </a>
                </div>
            <?php else: ?>
                <p style="margin-top: 1rem; color: #dc3545;">
                    <strong>You have used all <?php echo $maxAttempts; ?> attempts.</strong> Please contact your teacher for assistance.
                </p>
                <a href="lesson.php?id=<?php echo $lessonId; ?>" class="btn btn-secondary btn-sm" style="margin-top: 0.5rem;">
                    <i class="fas fa-book"></i> Review Lesson
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (count($allAttempts) > 1): ?>
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h2 class="card-title">All Attempts</h2>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Attempt</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allAttempts as $attempt): ?>
                        <tr style="<?php echo ($attempt['is_best_score'] ?? false) ? 'background: #d4edda;' : ''; ?>">
                            <td>
                                <?php echo $attempt['attempt_number'] ?? 'N/A'; ?>
                                <?php if ($attempt['is_best_score'] ?? false): ?>
                                    <span class="badge badge-success">Best</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($attempt['score'], 2); ?> / <?php echo number_format($attempt['total_points'], 2); ?></td>
                            <td><strong><?php echo number_format($attempt['percentage'], 2); ?>%</strong></td>
                            <td>
                                <?php if ($attempt['passed']): ?>
                                    <span class="badge badge-success">Passed</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($attempt['taken_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 2rem;">
        <a href="lessons.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Lessons
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
