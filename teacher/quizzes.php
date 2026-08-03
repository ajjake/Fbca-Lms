<?php
require_once '../config/config.php';
requireRole(['teacher']);

$conn = getDBConnection();
$teacherId = getCurrentUserId();
$selectedSubject = $_GET['subject_id'] ?? 0;
$selectedLesson = $_GET['lesson_id'] ?? 0;

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
        SELECT l.* FROM lessons l
        WHERE l.subject_id = ?
        ORDER BY l.quarter, l.order_index
    ");
    $stmt->bind_param("i", $selectedSubject);
    $stmt->execute();
    $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get quizzes for selected lesson
$quizzes = [];
if ($selectedLesson) {
    $stmt = $conn->prepare("
        SELECT q.*, l.title as lesson_title, l.lesson_number
        FROM quizzes q
        INNER JOIN lessons l ON q.lesson_id = l.id
        WHERE q.lesson_id = ?
    ");
    $stmt->bind_param("i", $selectedLesson);
    $stmt->execute();
    $quizzes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

closeDBConnection($conn);

$pageTitle = 'Manage Quizzes';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Manage Quizzes</h1>
        <?php if ($selectedLesson): ?>
            <a href="quiz-add.php?lesson_id=<?php echo $selectedLesson; ?>" class="btn btn-primary">Add Quiz</a>
        <?php endif; ?>
    </div>
    
    <div class="grid grid-2">
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
        
        <?php if ($selectedSubject): ?>
            <div class="form-group">
                <label class="form-label">Select Lesson</label>
                <select class="form-control" onchange="window.location.href='?subject_id=<?php echo $selectedSubject; ?>&lesson_id=' + this.value">
                    <option value="0">-- Select Lesson --</option>
                    <?php foreach ($lessons as $lesson): ?>
                        <option value="<?php echo $lesson['id']; ?>" <?php echo $selectedLesson == $lesson['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lesson['lesson_number']); ?> - <?php echo htmlspecialchars($lesson['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($selectedLesson && !empty($quizzes)): ?>
        <?php foreach ($quizzes as $quiz): ?>
            <div class="card" style="margin-top: 1.5rem;">
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                    <div>
                        <a href="quiz-edit.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="quiz-questions.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-question-circle"></i> Manage Questions
                        </a>
                    </div>
                </div>
                <p><strong>Passing Score:</strong> <?php echo number_format($quiz['passing_score'], 0); ?>%</p>
                <p><strong>Time Limit:</strong> <?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' minutes' : 'No limit'; ?></p>
            </div>
        <?php endforeach; ?>
    <?php elseif ($selectedLesson): ?>
        <div class="alert alert-info" style="margin-top: 1.5rem;">
            <i class="fas fa-info-circle"></i> No quiz found for this lesson. <a href="quiz-add.php?lesson_id=<?php echo $selectedLesson; ?>">Create a quiz</a>.
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
