<?php
use App\Application\PaceService;
use App\Application\StudentLessonService;

require_once '../config/config.php';
requireRole(['student']);

$lessonId = (int) ($_GET['id'] ?? 0);
if (!$lessonId) {
    header('Location: lessons.php');
    exit();
}

$studentId = getCurrentUserId();
$service = new StudentLessonService();
$paceService = new PaceService();

$lesson = $service->getLessonDetails($lessonId, $studentId);
if (!$lesson) {
    header('Location: lessons.php');
    exit();
}

$status = $lesson['status'] ?? 'locked';

if (($status === 'locked' || $status === null) && ($lesson['pace_type'] ?? 'lesson') !== 'lesson') {
    if (!$paceService->canAccessTest($studentId, $lessonId)) {
        header('Location: lessons.php?error=' . urlencode('You must complete the required PACEs before taking this test.'));
        exit();
    }

    $service->setLessonProgress($studentId, $lessonId, 'unlocked');
    $status = 'unlocked';
}

if ($status === 'locked' || $status === null) {
    header('Location: lessons.php');
    exit();
}

if ($status !== 'completed') {
    $service->setLessonProgress($studentId, $lessonId, 'in_progress');
}

$quiz = $service->getQuizByLesson($lessonId);
$examRequest = $service->getExamRequest($studentId, $lessonId);
$quizTaken = $service->getLatestQuizAttempt($studentId, $lessonId);

$totalAttempts = 0;
$bestPercentage = 0;
$hasPassed = 0;
$maxAttempts = 3;
if ($quizTaken) {
    $totalAttempts = $quizTaken['total_attempts'] ?? 1;
    $bestPercentage = $quizTaken['best_percentage'] ?? $quizTaken['percentage'];
    $hasPassed = $quizTaken['has_passed'] ?? ($quizTaken['passed'] ? 1 : 0);
}
$remainingAttempts = $maxAttempts - $totalAttempts;

$pageTitle = $lesson['title'];
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title"><?php echo htmlspecialchars($lesson['title']); ?></h1>
        <a href="lessons.php" class="btn btn-secondary">Back to Lessons</a>
    </div>
    
    <div style="margin-bottom: 1rem;">
        <span class="badge badge-info"><?php echo htmlspecialchars($lesson['subject_name']); ?></span>
        <span class="badge badge-info"><?php echo htmlspecialchars($lesson['pace_number'] ?? $lesson['lesson_number']); ?></span>
        <?php if (($lesson['pace_type'] ?? 'lesson') !== 'lesson'): ?>
            <span class="badge badge-warning">
                <?php 
                $paceType = $lesson['pace_type'] ?? 'lesson';
                echo $paceType === 'monthly_test' ? 'Monthly Test' : 
                    ($paceType === 'quarter_test' ? 'Quarter Test' : ''); 
                ?>
            </span>
        <?php endif; ?>
        <span class="badge badge-info">Quarter <?php echo $lesson['quarter']; ?></span>
    </div>
    
    <?php if ($lesson['description']): ?>
        <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
            <p><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($lesson['image_file']): ?>
        <div style="margin-bottom: 1.5rem; text-align: center;">
            <img src="<?php echo BASE_URL . 'uploads/images/' . htmlspecialchars($lesson['image_file']); ?>" 
                 alt="<?php echo htmlspecialchars($lesson['title']); ?>" 
                 style="max-width: 100%; height: auto; border-radius: 5px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        </div>
    <?php endif; ?>
    
    <?php if ($lesson['content']): ?>
        <div style="margin-bottom: 1.5rem; padding: 1.5rem; background: #fff; border-radius: 5px; border: 1px solid #ddd;">
            <div style="line-height: 1.6;">
                <?php echo $lesson['content']; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($lesson['video_url'] || $lesson['video_file']): ?>
        <div class="video-container">
            <?php if ($lesson['video_url']): ?>
                <!-- YouTube or other embedded video -->
                <?php
                $videoUrl = $lesson['video_url'];
                // Convert YouTube URL to embed format
                if (strpos($videoUrl, 'youtube.com') !== false || strpos($videoUrl, 'youtu.be') !== false) {
                    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $videoUrl, $matches);
                    if (isset($matches[1])) {
                        $videoId = $matches[1];
                        $videoUrl = "https://www.youtube.com/embed/" . $videoId;
                    }
                }
                ?>
                <iframe src="<?php echo htmlspecialchars($videoUrl); ?>" allowfullscreen></iframe>
            <?php elseif ($lesson['video_file']): ?>
                <!-- Uploaded video file -->
                <video controls>
                    <source src="<?php echo BASE_URL . 'uploads/videos/' . htmlspecialchars($lesson['video_file']); ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($lesson['material_file']): ?>
        <div style="margin: 1.5rem 0;">
            <?php
            $fileExt = strtolower(pathinfo($lesson['material_file'], PATHINFO_EXTENSION));
            $fileType = 'File';
            if ($fileExt === 'pdf') $fileType = 'PDF';
            elseif (in_array($fileExt, ['doc', 'docx'])) $fileType = 'Word Document';
            ?>
            <a href="<?php echo BASE_URL . 'uploads/materials/' . htmlspecialchars($lesson['material_file']); ?>" 
               class="btn btn-primary" download>
                <i class="fas fa-download"></i> Download Materials (<?php echo $fileType; ?>)
            </a>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #ddd;">
        <h3>Quiz & Exam</h3>
        
        <?php if ($quizTaken): ?>
            <?php if ($hasPassed): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>Quiz Passed!</strong> Your best score: <?php echo number_format($bestPercentage, 2); ?>%
                    <span class="badge badge-success">Passed</span>
                </div>
                <a href="quiz-result.php?lesson_id=<?php echo $lessonId; ?>" class="btn btn-primary">
                    <i class="fas fa-chart-line"></i> View Quiz Results
                </a>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Quiz Not Passed</strong> - Your best score: <?php echo number_format($bestPercentage, 2); ?>%
                    <span class="badge badge-danger">Failed</span>
                    <?php if ($remainingAttempts > 0): ?>
                        <p style="margin-top: 0.5rem;">
                            You have <strong><?php echo $remainingAttempts; ?></strong> retake attempt(s) remaining.
                        </p>
                        <div style="margin-top: 0.5rem;">
                            <a href="quiz-result.php?lesson_id=<?php echo $lessonId; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-chart-line"></i> View Results
                            </a>
                            <a href="take-quiz.php?quiz_id=<?php echo $quiz['id']; ?>&lesson_id=<?php echo $lessonId; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-redo"></i> Retake Quiz (Attempt <?php echo $totalAttempts + 1; ?>)
                            </a>
                        </div>
                    <?php else: ?>
                        <p style="margin-top: 0.5rem; color: #dc3545;">
                            <strong>No retake attempts remaining.</strong> Please contact your teacher.
                        </p>
                        <a href="quiz-result.php?lesson_id=<?php echo $lessonId; ?>" class="btn btn-secondary btn-sm" style="margin-top: 0.5rem;">
                            <i class="fas fa-chart-line"></i> View Results
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($examRequest && $examRequest['status'] === 'approved'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Your exam request has been approved! You can now take the quiz.
            </div>
            <?php if ($quiz): ?>
                <a href="take-quiz.php?quiz_id=<?php echo $quiz['id']; ?>&lesson_id=<?php echo $lessonId; ?>" class="btn btn-success">
                    <i class="fas fa-question-circle"></i> Take Quiz
                </a>
            <?php endif; ?>
        <?php elseif ($examRequest && $examRequest['status'] === 'pending'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-clock"></i>
                Your exam request is pending approval. Please wait for your teacher to approve.
            </div>
        <?php elseif ($examRequest && $examRequest['status'] === 'denied'): ?>
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i>
                Your exam request has been denied.
                <?php if ($examRequest['remarks']): ?>
                    <br><strong>Remarks:</strong> <?php echo htmlspecialchars($examRequest['remarks']); ?>
                <?php endif; ?>
            </div>
            <button onclick="requestExam(<?php echo $lessonId; ?>)" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Request Exam Again
            </button>
        <?php else: ?>
            <?php if ($quiz): ?>
                <p>To take the quiz for this lesson, you need to request exam approval from your teacher.</p>
                <button onclick="requestExam(<?php echo $lessonId; ?>)" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Request Exam Approval
                </button>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    No quiz available for this lesson yet.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function requestExam(lessonId) {
    if (confirm('Are you sure you want to request exam approval for this lesson?')) {
        fetch('../api/request-exam.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lesson_id: lessonId,
                request_type: 'lesson_exam'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Exam request submitted successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to submit request'));
            }
        })
        .catch(error => {
            alert('Error: ' + error);
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>
