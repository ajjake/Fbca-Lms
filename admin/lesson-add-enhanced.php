<?php
require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();
$message = '';
$error = '';

// Get all subjects
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lesson data
    $subjectId = $_POST['subject_id'] ?? 0;
    $lessonNumber = $_POST['lesson_number'] ?? '';
    $paceNumber = $_POST['lesson_number'] ?? ''; // Use same as lesson_number for now
    $paceType = $_POST['pace_type'] ?? 'lesson';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $content = $_POST['content'] ?? '';
    $quarter = $_POST['quarter'] ?? 1;
    $level = $_POST['level'] ?? 1;
    $videoUrl = $_POST['video_url'] ?? '';
    $orderIndex = $_POST['order_index'] ?? 0;
    
    // Quiz data
    $createQuiz = isset($_POST['create_quiz']) && $_POST['create_quiz'] == '1';
    $quizTitle = $_POST['quiz_title'] ?? '';
    $quizQuarter = $_POST['quiz_quarter'] ?? $quarter;
    $passingScore = $_POST['passing_score'] ?? 75.00;
    $timeLimit = $_POST['time_limit'] ?? 0;
    $questions = $_POST['questions'] ?? [];
    
    // Validate
    if (empty($subjectId) || empty($lessonNumber) || empty($title)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if lesson number already exists
        $stmt = $conn->prepare("SELECT id FROM lessons WHERE lesson_number = ?");
        $stmt->bind_param("s", $lessonNumber);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Lesson number already exists.';
        } else {
            // Handle file uploads
            $videoFile = '';
            $materialFile = '';
            $imageFile = '';
            
            // Video file
            if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = UPLOAD_PATH_VIDEOS;
                $fileName = time() . '_' . basename($_FILES['video_file']['name']);
                if (move_uploaded_file($_FILES['video_file']['tmp_name'], $uploadDir . $fileName)) {
                    $videoFile = $fileName;
                }
            }
            
            // Material file
            if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = UPLOAD_PATH_MATERIALS;
                $fileName = time() . '_' . basename($_FILES['material_file']['name']);
                if (move_uploaded_file($_FILES['material_file']['tmp_name'], $uploadDir . $fileName)) {
                    $materialFile = $fileName;
                }
            }
            
            // Image file
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = UPLOAD_PATH_IMAGES;
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = $_FILES['image_file']['type'];
                
                if (in_array($fileType, $allowedTypes)) {
                    $fileName = time() . '_' . basename($_FILES['image_file']['name']);
                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $fileName)) {
                        $imageFile = $fileName;
                    }
                } else {
                    $error = 'Invalid image file type. Allowed: JPG, PNG, GIF, WEBP';
                }
            }
            
            if (empty($error)) {
                // Insert lesson
                $stmt = $conn->prepare("
                    INSERT INTO lessons (subject_id, lesson_number, pace_number, pace_type, title, description, content, quarter, level, 
                                       video_url, video_file, material_file, image_file, order_index)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("issssssiissssi", $subjectId, $lessonNumber, $paceNumber, $paceType, $title, $description, $content, 
                                 $quarter, $level, $videoUrl, $videoFile, $materialFile, $imageFile, $orderIndex);
                
                if ($stmt->execute()) {
                    $lessonId = $conn->insert_id;
                    $stmt->close();
                    
                    // Create quiz if requested
                    if ($createQuiz && !empty($quizTitle) && !empty($questions)) {
                        // Insert quiz
                        $quizStmt = $conn->prepare("
                            INSERT INTO quizzes (lesson_id, title, passing_score, time_limit)
                            VALUES (?, ?, ?, ?)
                        ");
                        $quizStmt->bind_param("isdi", $lessonId, $quizTitle, $passingScore, $timeLimit);
                        
                        if ($quizStmt->execute()) {
                            $quizId = $conn->insert_id;
                            $quizStmt->close();
                            
                            // Insert questions
                            $questionStmt = $conn->prepare("
                                INSERT INTO quiz_questions (quiz_id, question, question_type, option_a, option_b, 
                                                           option_c, option_d, correct_answer, points, order_index)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            $orderIndexQ = 0;
                            foreach ($questions as $q) {
                                if (!empty($q['question']) && !empty($q['correct_answer'])) {
                                    $qType = $q['question_type'] ?? 'multiple_choice';
                                    $orderIndexQ++;
                                    
                                    // Extract values to variables for bind_param
                                    $questionText = $q['question'];
                                    $optionA = $q['option_a'] ?? '';
                                    $optionB = $q['option_b'] ?? '';
                                    $optionC = $q['option_c'] ?? '';
                                    $optionD = $q['option_d'] ?? '';
                                    $correctAnswer = $q['correct_answer'];
                                    $points = $q['points'] ?? 1.00;
                                    
                                    $questionStmt->bind_param("isssssssdi", 
                                        $quizId,
                                        $questionText,
                                        $qType,
                                        $optionA,
                                        $optionB,
                                        $optionC,
                                        $optionD,
                                        $correctAnswer,
                                        $points,
                                        $orderIndexQ
                                    );
                                    $questionStmt->execute();
                                }
                            }
                            $questionStmt->close();
                            $message = 'Lesson and quiz added successfully!';
                        } else {
                            $message = 'Lesson added successfully, but quiz creation failed.';
                        }
                    } else {
                        $message = 'Lesson added successfully!';
                    }
                    
                    // Redirect to show success
                    header('Location: lesson-add-enhanced.php?msg=' . urlencode($message));
                    exit();
                } else {
                    $error = 'Failed to add lesson: ' . $conn->error;
                }
            }
        }
        $stmt->close();
    }
}

// Check for success message
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

closeDBConnection($conn);

$pageTitle = 'Add Lesson (Enhanced)';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Add New Lesson (Enhanced)</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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
    
    <form method="POST" enctype="multipart/form-data" id="lessonForm">
        <h2 style="margin-top: 2rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;">Lesson Information</h2>
        
        <div class="form-group">
            <label class="form-label" for="subject_id">Subject *</label>
            <select class="form-control" id="subject_id" name="subject_id" required>
                <option value="">-- Select Subject --</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="grid grid-2">
        <div class="form-group">
            <label class="form-label" for="lesson_number">PACE Number *</label>
            <input type="text" class="form-control" id="lesson_number" name="lesson_number" 
                   placeholder="e.g., ENG 1013" required>
            <small>This will also be used as the PACE number</small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="pace_type">PACE Type *</label>
            <select class="form-control" id="pace_type" name="pace_type" required>
                <option value="lesson">Regular PACE (Lesson)</option>
                <option value="monthly_test">Monthly Test (after 2 PACEs)</option>
                <option value="quarter_test">Quarter Test (after 3 PACEs)</option>
            </select>
        </div>
            
            <div class="form-group">
                <label class="form-label" for="title">Title *</label>
                <input type="text" class="form-control" id="title" name="title" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="description">Short Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="content">Content (Text/HTML)</label>
            <textarea class="form-control" id="content" name="content" rows="10" 
                      placeholder="Enter lesson content here. You can use HTML for formatting."></textarea>
            <small>You can use HTML tags for formatting (e.g., &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;, &lt;li&gt;)</small>
        </div>
        
        <div class="grid grid-3">
            <div class="form-group">
                <label class="form-label" for="quarter">Quarter *</label>
                <select class="form-control" id="quarter" name="quarter" required>
                    <option value="1">Quarter 1</option>
                    <option value="2">Quarter 2</option>
                    <option value="3">Quarter 3</option>
                    <option value="4">Quarter 4</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="level">Level *</label>
                <input type="number" class="form-control" id="level" name="level" value="1" min="1" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="order_index">Order Index</label>
                <input type="number" class="form-control" id="order_index" name="order_index" value="0" min="0">
            </div>
        </div>
        
        <h2 style="margin-top: 2rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;">Media Files</h2>
        
        <div class="form-group">
            <label class="form-label" for="image_file">Image/Photo</label>
            <input type="file" class="form-control" id="image_file" name="image_file" 
                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
            <small>Supported formats: JPG, PNG, GIF, WEBP</small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="video_url">Video URL (YouTube or other)</label>
            <input type="url" class="form-control" id="video_url" name="video_url" 
                   placeholder="https://www.youtube.com/watch?v=...">
        </div>
        
        <div class="form-group">
            <label class="form-label" for="video_file">Or Upload Video File (MP4)</label>
            <input type="file" class="form-control" id="video_file" name="video_file" accept="video/mp4">
        </div>
        
        <div class="form-group">
            <label class="form-label" for="material_file">Material File (PDF, DOC, DOCX)</label>
            <input type="file" class="form-control" id="material_file" name="material_file" 
                   accept="application/pdf,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        </div>
        
        <h2 style="margin-top: 2rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;">Quiz (Optional)</h2>
        
        <div class="form-group">
            <label>
                <input type="checkbox" id="create_quiz" name="create_quiz" value="1" onchange="toggleQuizSection()">
                Create Quiz for this Lesson
            </label>
        </div>
        
        <div id="quizSection" style="display: none;">
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="quiz_title">Quiz Title *</label>
                    <input type="text" class="form-control" id="quiz_title" name="quiz_title">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="quiz_quarter">Quiz Quarter *</label>
                    <select class="form-control" id="quiz_quarter" name="quiz_quarter">
                        <option value="1">Quarter 1</option>
                        <option value="2">Quarter 2</option>
                        <option value="3">Quarter 3</option>
                        <option value="4">Quarter 4</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="passing_score">Passing Score (%)</label>
                    <input type="number" class="form-control" id="passing_score" name="passing_score" 
                           value="75" min="0" max="100" step="0.01">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="time_limit">Time Limit (minutes, 0 = no limit)</label>
                    <input type="number" class="form-control" id="time_limit" name="time_limit" value="0" min="0">
                </div>
            </div>
            
            <div id="questionsContainer">
                <h3 style="margin-top: 1rem;">Questions</h3>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addQuestion()">
                    <i class="fas fa-plus"></i> Add Question
                </button>
                
                <div id="questionsList"></div>
            </div>
        </div>
        
        <div style="margin-top: 2rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Lesson
            </button>
        </div>
    </form>
</div>

<script>
let questionCount = 0;

function toggleQuizSection() {
    const checkbox = document.getElementById('create_quiz');
    const section = document.getElementById('quizSection');
    section.style.display = checkbox.checked ? 'block' : 'none';
    
    // Sync quiz quarter with lesson quarter
    if (checkbox.checked) {
        const lessonQuarter = document.getElementById('quarter').value;
        document.getElementById('quiz_quarter').value = lessonQuarter;
    }
}

function addQuestion() {
    questionCount++;
    const container = document.getElementById('questionsList');
    const questionDiv = document.createElement('div');
    questionDiv.className = 'question-item';
    questionDiv.style.border = '1px solid #ddd';
    questionDiv.style.padding = '1rem';
    questionDiv.style.marginTop = '1rem';
    questionDiv.style.borderRadius = '4px';
    questionDiv.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h4>Question ${questionCount}</h4>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeQuestion(this)">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        <div class="form-group">
            <label class="form-label">Question *</label>
            <textarea class="form-control" name="questions[${questionCount}][question]" rows="2" required></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Question Type *</label>
            <select class="form-control" name="questions[${questionCount}][question_type]" onchange="toggleQuestionType(this)" required>
                <option value="multiple_choice">Multiple Choice</option>
                <option value="true_false">True/False</option>
            </select>
        </div>
        <div class="question-options">
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Option A *</label>
                    <input type="text" class="form-control" name="questions[${questionCount}][option_a]" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Option B *</label>
                    <input type="text" class="form-control" name="questions[${questionCount}][option_b]" required>
                </div>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Option C</label>
                    <input type="text" class="form-control" name="questions[${questionCount}][option_c]">
                </div>
                <div class="form-group">
                    <label class="form-label">Option D</label>
                    <input type="text" class="form-control" name="questions[${questionCount}][option_d]">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Correct Answer *</label>
            <select class="form-control" name="questions[${questionCount}][correct_answer]" required>
                <option value="">-- Select --</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Points</label>
            <input type="number" class="form-control" name="questions[${questionCount}][points]" value="1.00" step="0.01" min="0">
        </div>
    `;
    container.appendChild(questionDiv);
}

function removeQuestion(btn) {
    btn.closest('.question-item').remove();
}

function toggleQuestionType(select) {
    const questionDiv = select.closest('.question-item');
    const optionsDiv = questionDiv.querySelector('.question-options');
    const correctAnswerSelect = questionDiv.querySelector('select[name*="[correct_answer]"]');
    
    if (select.value === 'true_false') {
        // Hide C and D options, show True/False
        const optionC = optionsDiv.querySelector('input[name*="[option_c]"]').closest('.form-group');
        const optionD = optionsDiv.querySelector('input[name*="[option_d]"]').closest('.form-group');
        optionC.style.display = 'none';
        optionD.style.display = 'none';
        
        // Update option labels
        const optionA = optionsDiv.querySelector('input[name*="[option_a]"]');
        const optionB = optionsDiv.querySelector('input[name*="[option_b]"]');
        optionA.value = 'True';
        optionA.readOnly = true;
        optionB.value = 'False';
        optionB.readOnly = true;
        
        // Update correct answer options
        correctAnswerSelect.innerHTML = `
            <option value="">-- Select --</option>
            <option value="True">True</option>
            <option value="False">False</option>
        `;
    } else {
        // Show all options
        const optionC = optionsDiv.querySelector('input[name*="[option_c]"]').closest('.form-group');
        const optionD = optionsDiv.querySelector('input[name*="[option_d]"]').closest('.form-group');
        optionC.style.display = 'block';
        optionD.style.display = 'block';
        
        // Clear and enable inputs
        const optionA = optionsDiv.querySelector('input[name*="[option_a]"]');
        const optionB = optionsDiv.querySelector('input[name*="[option_b]"]');
        optionA.value = '';
        optionA.readOnly = false;
        optionB.value = '';
        optionB.readOnly = false;
        
        // Update correct answer options
        correctAnswerSelect.innerHTML = `
            <option value="">-- Select --</option>
            <option value="A">A</option>
            <option value="B">B</option>
            <option value="C">C</option>
            <option value="D">D</option>
        `;
    }
}

// Sync lesson quarter with quiz quarter
document.getElementById('quarter').addEventListener('change', function() {
    if (document.getElementById('create_quiz').checked) {
        document.getElementById('quiz_quarter').value = this.value;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
