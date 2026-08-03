<?php
require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();

// Filter options
$subjectFilter = $_GET['subject_id'] ?? 'all';
$quarterFilter = $_GET['quarter'] ?? 'all';
$levelFilter = $_GET['level'] ?? 'all';
$typeFilter = $_GET['pace_type'] ?? 'all'; // lesson | monthly_test | quarter_test | all

// Build query
$query = "
    SELECT l.*, s.name as subject_name, s.code as subject_code
    FROM lessons l
    INNER JOIN subjects s ON l.subject_id = s.id
    WHERE 1=1
";

$params = [];
$types = '';

if ($subjectFilter !== 'all') {
    $query .= " AND l.subject_id = ?";
    $params[] = $subjectFilter;
    $types .= 'i';
}

if ($quarterFilter !== 'all') {
    $query .= " AND l.quarter = ?";
    $params[] = $quarterFilter;
    $types .= 'i';
}

if ($levelFilter !== 'all') {
    $query .= " AND l.level = ?";
    $params[] = $levelFilter;
    $types .= 'i';
}

$allowedTypeFilters = ['all', 'lesson', 'monthly_test', 'quarter_test'];
if (!in_array($typeFilter, $allowedTypeFilters, true)) {
    $typeFilter = 'all';
}
if ($typeFilter !== 'all') {
    $query .= " AND l.pace_type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}

$query .= " ORDER BY l.subject_id, l.quarter, l.order_index, l.lesson_number";

// Get all subjects for filter
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $lessons = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Get lesson counts
$totalLessons = $conn->query("SELECT COUNT(*) as total FROM lessons")->fetch_assoc()['total'];

closeDBConnection($conn);

$subjectColors = [
    'ENG' => '#e74c3c', // English - Red
    'SCI' => '#3498db', // Science - Blue
    'MATH' => '#f1c40f', // Math - Yellow
    'WB' => '#9b59b6', // Word Building - Purple
    'COMP' => '#e67e22', // Computer - Orange
    'FIL' => '#7f8c8d', // Filipino - Gray
    'AP' => '#8e6b3a', // Araling Panlipunan - Brown
];

$pageTitle = 'Manage Lessons';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Manage Lessons</h1>
        <div>
            <a href="bulk-create-paces.php" class="btn btn-primary">
                <i class="fas fa-layer-group"></i> Bulk Create PACEs
            </a>
            <a href="create-tests.php" class="btn btn-primary">
                <i class="fas fa-clipboard-check"></i> Create Tests
            </a>
        </div>
    </div>
    
    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
        <div class="grid grid-3">
            <div class="form-group">
                <label class="form-label" for="subject_filter">Filter by Subject</label>
                <select class="form-control" id="subject_filter" onchange="applyFilters()">
                    <option value="all" <?php echo $subjectFilter === 'all' ? 'selected' : ''; ?>>All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subjectFilter == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="quarter_filter">Filter by Quarter</label>
                <select class="form-control" id="quarter_filter" onchange="applyFilters()">
                    <option value="all" <?php echo $quarterFilter === 'all' ? 'selected' : ''; ?>>All Quarters</option>
                    <option value="1" <?php echo $quarterFilter == '1' ? 'selected' : ''; ?>>Quarter 1</option>
                    <option value="2" <?php echo $quarterFilter == '2' ? 'selected' : ''; ?>>Quarter 2</option>
                    <option value="3" <?php echo $quarterFilter == '3' ? 'selected' : ''; ?>>Quarter 3</option>
                    <option value="4" <?php echo $quarterFilter == '4' ? 'selected' : ''; ?>>Quarter 4</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="level_filter">Filter by Level</label>
                <select class="form-control" id="level_filter" onchange="applyFilters()">
                    <option value="all" <?php echo $levelFilter === 'all' ? 'selected' : ''; ?>>All Levels</option>
                    <option value="1" <?php echo $levelFilter == '1' ? 'selected' : ''; ?>>Level 1</option>
                    <option value="2" <?php echo $levelFilter == '2' ? 'selected' : ''; ?>>Level 2</option>
                    <option value="3" <?php echo $levelFilter == '3' ? 'selected' : ''; ?>>Level 3</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="type_filter">Filter by Type</label>
                <select class="form-control" id="type_filter" onchange="applyFilters()">
                    <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="lesson" <?php echo $typeFilter === 'lesson' ? 'selected' : ''; ?>>PACEs</option>
                    <option value="monthly_test" <?php echo $typeFilter === 'monthly_test' ? 'selected' : ''; ?>>Monthly Tests</option>
                    <option value="quarter_test" <?php echo $typeFilter === 'quarter_test' ? 'selected' : ''; ?>>Quarter Tests</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top: 1rem;">
            <a href="lessons.php" class="btn btn-secondary btn-sm">Clear Filters</a>
            <span style="margin-left: 1rem; color: #666;">
                Total Lessons: <strong><?php echo $totalLessons; ?></strong> | 
                Showing: <strong><?php echo count($lessons); ?></strong>
            </span>
        </div>
    </div>
    
    <?php if (!empty($lessons)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Lesson Number</th>
                        <th>Title</th>
                        <th>Subject</th>
                        <th>Quarter</th>
                        <th>Level</th>
                        <th>Media</th>
                        <th>Quiz</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $lastSection = null;
                    foreach ($lessons as $lesson):
                        $section = $lesson['pace_type'] ?? 'lesson';
                        if ($section !== $lastSection):
                            $lastSection = $section;
                    ?>
                        <tr>
                            <td colspan="8" style="background:#f1f3f5; font-weight:700;">
                                <?php
                                echo $section === 'lesson' ? 'PACEs' : ($section === 'monthly_test' ? 'Monthly Tests' : 'Quarter Tests');
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                        <?php
                        $code = $lesson['subject_code'] ?? '';
                        $borderColor = $subjectColors[$code] ?? '#ccc';
                        ?>
                        <tr style="border-left: 4px solid <?php echo $borderColor; ?>;">
                            <td>
                                <?php echo htmlspecialchars($lesson['pace_number'] ?? $lesson['lesson_number']); ?>
                                <?php if (($lesson['pace_type'] ?? 'lesson') !== 'lesson'): ?>
                                    <span class="badge badge-warning" style="margin-left: 0.5rem;">
                                        <?php 
                                        $paceType = $lesson['pace_type'] ?? 'lesson';
                                        echo $paceType === 'monthly_test' ? 'Monthly Test' : 
                                            ($paceType === 'quarter_test' ? 'Quarter Test' : ''); 
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo htmlspecialchars($lesson['subject_name']); ?>
                                </span>
                            </td>
                            <td>Q<?php echo $lesson['quarter']; ?></td>
                            <td>Level <?php echo $lesson['level']; ?></td>
                            <td>
                                <?php
                                $mediaCount = 0;
                                if ($lesson['video_url'] || $lesson['video_file']) $mediaCount++;
                                if ($lesson['image_file']) $mediaCount++;
                                if ($lesson['material_file']) $mediaCount++;
                                ?>
                                <span class="badge badge-<?php echo $mediaCount > 0 ? 'success' : 'secondary'; ?>">
                                    <?php echo $mediaCount; ?> file(s)
                                </span>
                            </td>
                            <td>
                                <?php
                                $conn2 = getDBConnection();
                                $quizStmt = $conn2->prepare("SELECT id FROM quizzes WHERE lesson_id = ?");
                                $quizStmt->bind_param("i", $lesson['id']);
                                $quizStmt->execute();
                                $hasQuiz = $quizStmt->get_result()->num_rows > 0;
                                $quizStmt->close();
                                closeDBConnection($conn2);
                                ?>
                                <?php if ($hasQuiz): ?>
                                    <span class="badge badge-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="lesson-edit.php?id=<?php echo $lesson['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="lesson-delete.php?id=<?php echo $lesson['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this lesson? This will also delete associated quizzes and student progress.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No lessons found. 
            <a href="bulk-create-paces.php">Create PACEs using Bulk Create</a>.
        </div>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const subject = document.getElementById('subject_filter').value;
    const quarter = document.getElementById('quarter_filter').value;
    const level = document.getElementById('level_filter').value;
    const type = document.getElementById('type_filter').value;
    
    const params = new URLSearchParams();
    if (subject !== 'all') params.append('subject_id', subject);
    if (quarter !== 'all') params.append('quarter', quarter);
    if (level !== 'all') params.append('level', level);
    if (type !== 'all') params.append('pace_type', type);
    
    window.location.href = 'lessons.php?' + params.toString();
}
</script>

<?php include '../includes/footer.php'; ?>
