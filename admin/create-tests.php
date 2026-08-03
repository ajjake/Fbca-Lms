<?php
require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();
$message = '';
$error = '';

// Get all subjects
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Prefill from query string (e.g. redirect from Bulk Create PACEs)
$prefillLevel = isset($_GET['level']) ? (int)$_GET['level'] : 1;
$prefillQuarter = isset($_GET['quarter']) ? (int)$_GET['quarter'] : 1;
$prefillTestType = $_GET['test_type'] ?? '';
$prefillSubjectsCsv = $_GET['subjects'] ?? '';
$prefillSubjectIds = [];
if (is_string($prefillSubjectsCsv) && $prefillSubjectsCsv !== '') {
    $prefillSubjectIds = array_values(array_filter(array_map('intval', explode(',', $prefillSubjectsCsv))));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testType = $_POST['test_type'] ?? '';
    $level = $_POST['level'] ?? 1;
    $quarter = $_POST['quarter'] ?? 1;
    $selectedSubjects = $_POST['subjects'] ?? [];
    
    if (empty($selectedSubjects) || empty($testType)) {
        $error = 'Please fill in all required fields.';
    } else {
        $created = 0;
        $skipped = 0;
        $firstQuizToEdit = null; // ['quiz_id' => int, 'lesson_id' => int]
        
        foreach ($selectedSubjects as $subjectId) {
            // Get subject info
            $subjectStmt = $conn->prepare("SELECT code, name FROM subjects WHERE id = ?");
            $subjectStmt->bind_param("i", $subjectId);
            $subjectStmt->execute();
            $subject = $subjectStmt->get_result()->fetch_assoc();
            $subjectStmt->close();
            
            if (!$subject) continue;
            
            $subjectCode = $subject['code'];
            
            // Determine PACE number based on test type and quarter
            // Monthly test: after 2nd PACE (e.g., 1014 for Q1, 1017 for Q2)
            // Quarter test: after 3rd PACE (e.g., 1015 for Q1, 1018 for Q2)
            $basePace = 1013 + ($level - 1) * 12 + ($quarter - 1) * 3;
            
            if ($testType === 'monthly_test') {
                $paceNumber = $basePace + 1; // After 2nd PACE
                $title = $subject['name'] . ' Monthly Test - Q' . $quarter;
            } else {
                $paceNumber = $basePace + 2; // After 3rd PACE
                $title = $subject['name'] . ' Quarter Test - Q' . $quarter;
            }
            
            $paceName = $subjectCode . ' ' . $paceNumber;
            
            // Check if already exists
            $checkStmt = $conn->prepare("SELECT id FROM lessons WHERE pace_number = ?");
            $checkStmt->bind_param("s", $paceName);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $skipped++;
                $checkStmt->close();
                continue;
            }
            $checkStmt->close();
            
            // Create test lesson
            $insertStmt = $conn->prepare("
                INSERT INTO lessons (subject_id, lesson_number, pace_number, title, pace_type, quarter, level, order_index)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $orderIndex = $testType === 'monthly_test' ? 2 : 3; // Monthly after 2nd, Quarter after 3rd
            $insertStmt->bind_param("issssiii", $subjectId, $paceName, $paceName, $title, $testType, $quarter, $level, $orderIndex);
            
            if ($insertStmt->execute()) {
                $created++;
                $testLessonId = $conn->insert_id;

                // Auto-create a quiz for the test so admin can add questions immediately
                $quizTitle = $title;
                $passing = 75.00;
                $timeLimit = 0;
                $quizStmt = $conn->prepare("
                    INSERT INTO quizzes (lesson_id, title, passing_score, time_limit)
                    VALUES (?, ?, ?, ?)
                ");
                if ($quizStmt) {
                    $quizStmt->bind_param("isdi", $testLessonId, $quizTitle, $passing, $timeLimit);
                    if ($quizStmt->execute()) {
                        $newQuizId = $conn->insert_id;
                        if ($firstQuizToEdit === null) {
                            $firstQuizToEdit = ['quiz_id' => $newQuizId, 'lesson_id' => $testLessonId];
                        }
                    }
                    $quizStmt->close();
                }
            }
            $insertStmt->close();
        }
        
        $message = "Created $created test(s). Skipped $skipped duplicate(s).";

        // After creating at least one test, go directly to adding questions
        if ($firstQuizToEdit !== null) {
            closeDBConnection($conn);
            header('Location: quiz-questions.php?quiz_id=' . $firstQuizToEdit['quiz_id'] . '&lesson_id=' . $firstQuizToEdit['lesson_id']);
            exit();
        }
    }
}

closeDBConnection($conn);

$pageTitle = 'Create Monthly/Quarter Tests';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Create Monthly/Quarter Tests</h1>
        <a href="bulk-create-paces.php" class="btn btn-secondary">Back to Create PACEs</a>
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
    
    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; margin-bottom: 2rem;">
        <h3>Test Requirements</h3>
        <ul>
            <li><strong>Monthly Test:</strong> Students must complete 2 PACEs before taking</li>
            <li><strong>Quarter Test:</strong> Students must complete 3 PACEs before taking</li>
            <li>Students must pass Quarter Test to advance to next quarter</li>
        </ul>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label class="form-label" for="test_type">Test Type *</label>
            <select class="form-control" id="test_type" name="test_type" required>
                <option value="">-- Select Test Type --</option>
                <option value="monthly_test" <?php echo ($prefillTestType === 'monthly_test') ? 'selected' : ''; ?>>Monthly Test (after 2nd PACE)</option>
                <option value="quarter_test" <?php echo ($prefillTestType === 'quarter_test') ? 'selected' : ''; ?>>Quarter Test (after 3rd PACE)</option>
            </select>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label" for="level">Level *</label>
                <input type="number" class="form-control" id="level" name="level" value="<?php echo (int)$prefillLevel; ?>" min="1" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="quarter">Quarter *</label>
                <select class="form-control" id="quarter" name="quarter" required>
                    <option value="1" <?php echo ((int)$prefillQuarter === 1) ? 'selected' : ''; ?>>Quarter 1</option>
                    <option value="2" <?php echo ((int)$prefillQuarter === 2) ? 'selected' : ''; ?>>Quarter 2</option>
                    <option value="3" <?php echo ((int)$prefillQuarter === 3) ? 'selected' : ''; ?>>Quarter 3</option>
                    <option value="4" <?php echo ((int)$prefillQuarter === 4) ? 'selected' : ''; ?>>Quarter 4</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Select Subjects *</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; margin-top: 0.5rem;">
                <?php foreach ($subjects as $subject): ?>
                    <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">
                        <input
                            type="checkbox"
                            name="subjects[]"
                            value="<?php echo $subject['id']; ?>"
                            style="margin-right: 0.5rem;"
                            <?php echo in_array((int)$subject['id'], $prefillSubjectIds, true) ? 'checked' : ''; ?>
                        >
                        <?php echo htmlspecialchars($subject['name']); ?> (<?php echo htmlspecialchars($subject['code']); ?>)
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Tests & Add Questions
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
