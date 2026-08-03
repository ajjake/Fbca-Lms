<?php
require_once '../config/config.php';
requireRole(['admin']);

$quizId = $_GET['quiz_id'] ?? 0;
$lessonId = $_GET['lesson_id'] ?? 0;

if (!$quizId) {
    header('Location: lessons.php');
    exit();
}

$conn = getDBConnection();

// Get quiz details (no teacher restriction for admin)
$stmt = $conn->prepare("
    SELECT q.*, l.title as lesson_title, l.lesson_number, l.id as lesson_id, s.name as subject_name
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

// Get questions
$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index, id");
$stmt->bind_param("i", $quizId);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $question = $_POST['question'] ?? '';
        $questionType = $_POST['question_type'] ?? 'multiple_choice';
        $optionA = $_POST['option_a'] ?? '';
        $optionB = $_POST['option_b'] ?? '';
        $optionC = $_POST['option_c'] ?? '';
        $optionD = $_POST['option_d'] ?? '';
        $correctAnswer = $_POST['correct_answer'] ?? '';
        $points = $_POST['points'] ?? 1;
        $orderIndex = $_POST['order_index'] ?? 0;
        
        if (empty($question) || empty($correctAnswer)) {
            $error = 'Please fill in all required fields.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO quiz_questions (quiz_id, question, question_type, option_a, option_b, option_c, option_d, correct_answer, points, order_index)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            // correct_answer is a STRING (A/B/C/D or True/False)
            $stmt->bind_param("isssssssdi", $quizId, $question, $questionType, $optionA, $optionB, $optionC, $optionD, $correctAnswer, $points, $orderIndex);
            
            if ($stmt->execute()) {
                $message = 'Question added successfully!';
                $stmt->close();
                // Reload questions
                $reloadStmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index, id");
                $reloadStmt->bind_param("i", $quizId);
                $reloadStmt->execute();
                $questions = $reloadStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $reloadStmt->close();
            } else {
                $error = 'Failed to add question: ' . $conn->error;
                $stmt->close();
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $questionId = $_POST['question_id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?");
        $stmt->bind_param("ii", $questionId, $quizId);
        if ($stmt->execute()) {
            header('Location: quiz-questions.php?quiz_id=' . $quizId . '&lesson_id=' . $lessonId);
            exit();
        } else {
            $error = 'Failed to delete question: ' . $conn->error;
        }
        $stmt->close();
    }
}

closeDBConnection($conn);

$pageTitle = 'Manage Quiz Questions';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Manage Quiz Questions</h1>
        <a href="quiz-edit.php?quiz_id=<?php echo $quizId; ?>&lesson_id=<?php echo $quiz['lesson_id']; ?>" class="btn btn-secondary">Back to Quiz</a>
    </div>
    
    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
        <p><strong>Quiz:</strong> <?php echo htmlspecialchars($quiz['title']); ?></p>
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
    
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <h2 class="card-title">Add New Question</h2>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label" for="question">Question *</label>
                <textarea class="form-control" id="question" name="question" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="question_type">Question Type *</label>
                <select class="form-control" id="question_type" name="question_type" required onchange="toggleOptions()">
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="true_false">True/False</option>
                </select>
            </div>
            
            <div id="options-container">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label" for="option_a">Option A *</label>
                        <input type="text" class="form-control" id="option_a" name="option_a" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="option_b">Option B *</label>
                        <input type="text" class="form-control" id="option_b" name="option_b" required>
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label" for="option_c">Option C</label>
                        <input type="text" class="form-control" id="option_c" name="option_c">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="option_d">Option D</label>
                        <input type="text" class="form-control" id="option_d" name="option_d">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="correct_answer">Correct Answer *</label>
                <select class="form-control" id="correct_answer" name="correct_answer" required>
                    <option value="">-- Select Answer --</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                    <option value="True">True</option>
                    <option value="False">False</option>
                </select>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="points">Points</label>
                    <input type="number" class="form-control" id="points" name="points" value="1" min="0.01" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label" for="order_index">Order Index</label>
                    <input type="number" class="form-control" id="order_index" name="order_index" value="0" min="0">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Question
            </button>
        </form>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Questions (<?php echo count($questions); ?>)</h2>
        </div>
        
        <?php if (empty($questions)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No questions added yet. Add questions above.
            </div>
        <?php else: ?>
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card" style="border: 1px solid #ddd; padding: 1rem; margin-bottom: 1rem; border-radius: 5px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div>
                            <strong>Question <?php echo $index + 1; ?>:</strong> <?php echo htmlspecialchars($question['question']); ?>
                            <span class="badge badge-info"><?php echo $question['points']; ?> points</span>
                        </div>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this question?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    
                    <?php if ($question['question_type'] === 'true_false'): ?>
                        <p><strong>Type:</strong> True/False</p>
                        <p><strong>Correct Answer:</strong> <span class="badge badge-success"><?php echo htmlspecialchars($question['correct_answer']); ?></span></p>
                    <?php else: ?>
                        <p><strong>Type:</strong> Multiple Choice</p>
                        <ul>
                            <?php if ($question['option_a']): ?>
                                <li>A. <?php echo htmlspecialchars($question['option_a']); ?> <?php echo $question['correct_answer'] === 'A' ? '<span class="badge badge-success">Correct</span>' : ''; ?></li>
                            <?php endif; ?>
                            <?php if ($question['option_b']): ?>
                                <li>B. <?php echo htmlspecialchars($question['option_b']); ?> <?php echo $question['correct_answer'] === 'B' ? '<span class="badge badge-success">Correct</span>' : ''; ?></li>
                            <?php endif; ?>
                            <?php if ($question['option_c']): ?>
                                <li>C. <?php echo htmlspecialchars($question['option_c']); ?> <?php echo $question['correct_answer'] === 'C' ? '<span class="badge badge-success">Correct</span>' : ''; ?></li>
                            <?php endif; ?>
                            <?php if ($question['option_d']): ?>
                                <li>D. <?php echo htmlspecialchars($question['option_d']); ?> <?php echo $question['correct_answer'] === 'D' ? '<span class="badge badge-success">Correct</span>' : ''; ?></li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleOptions() {
    const type = document.getElementById('question_type').value;
    const container = document.getElementById('options-container');
    const correctAnswer = document.getElementById('correct_answer');
    const optionA = document.getElementById('option_a');
    const optionB = document.getElementById('option_b');
    const optionC = document.getElementById('option_c');
    const optionD = document.getElementById('option_d');
    
    if (type === 'true_false') {
        container.style.display = 'none';
        optionA.value = 'True';
        optionA.readOnly = true;
        optionB.value = 'False';
        optionB.readOnly = true;
        optionC.value = '';
        optionD.value = '';
        correctAnswer.innerHTML = '<option value="">-- Select Answer --</option><option value="True">True</option><option value="False">False</option>';
    } else {
        container.style.display = 'block';
        optionA.value = '';
        optionA.readOnly = false;
        optionB.value = '';
        optionB.readOnly = false;
        correctAnswer.innerHTML = '<option value="">-- Select Answer --</option><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
