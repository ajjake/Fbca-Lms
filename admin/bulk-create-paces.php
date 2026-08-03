<?php
require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();
$message = '';
$error = '';

// Get all subjects
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $level = $_POST['level'] ?? 1;
    $quarter = $_POST['quarter'] ?? 1;
    $startPace = $_POST['start_pace'] ?? 0;
    $endPace = $_POST['end_pace'] ?? 0;
    $selectedSubjects = $_POST['subjects'] ?? [];
    
    if (empty($selectedSubjects) || $startPace <= 0 || $endPace <= 0 || $startPace > $endPace) {
        $error = 'Please fill in all required fields correctly.';
    } else {
        $created = 0;
        $skipped = 0;
        
        foreach ($selectedSubjects as $subjectId) {
            // Get subject code
            $subjectStmt = $conn->prepare("SELECT code, name FROM subjects WHERE id = ?");
            $subjectStmt->bind_param("i", $subjectId);
            $subjectStmt->execute();
            $subject = $subjectStmt->get_result()->fetch_assoc();
            $subjectStmt->close();
            
            if (!$subject) continue;
            
            $subjectCode = $subject['code'];
            $paceNumber = $startPace;
            
            // Create 3 PACEs for this quarter
            for ($i = 0; $i < 3; $i++) {
                $currentPace = $startPace + $i;
                if ($currentPace > $endPace) break;
                
                $paceName = $subjectCode . ' ' . $currentPace;
                
                // Check if already exists
                $checkStmt = $conn->prepare("SELECT id FROM lessons WHERE pace_number = ? OR lesson_number = ?");
                $checkStmt->bind_param("ss", $paceName, $paceName);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    $skipped++;
                    $checkStmt->close();
                    continue;
                }
                $checkStmt->close();
                
                // Create PACE lesson
                $insertStmt = $conn->prepare("
                    INSERT INTO lessons (subject_id, lesson_number, pace_number, title, pace_type, quarter, level, order_index)
                    VALUES (?, ?, ?, ?, 'lesson', ?, ?, ?)
                ");
                $title = $subject['name'] . ' PACE ' . $currentPace;
                $orderIndex = $i;
                $insertStmt->bind_param("issssii", $subjectId, $paceName, $paceName, $title, $quarter, $level, $orderIndex);
                
                if ($insertStmt->execute()) {
                    $created++;
                }
                $insertStmt->close();
            }
        }
        
        // After creating PACEs, jump straight to creating tests (prefilled)
        if ($created > 0) {
            closeDBConnection($conn);

            $subjectCsv = implode(',', array_map('intval', $selectedSubjects));
            $qs = http_build_query([
                'level' => (int)$level,
                'quarter' => (int)$quarter,
                'subjects' => $subjectCsv,
                'test_type' => 'monthly_test',
                'from' => 'bulk_create_paces',
            ]);
            header('Location: create-tests.php?' . $qs);
            exit();
        }

        $message = "Created $created PACE(s). Skipped $skipped duplicate(s).";
    }
}

closeDBConnection($conn);

$pageTitle = 'Bulk Create PACEs';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Bulk Create PACEs</h1>
        <a href="lessons.php" class="btn btn-secondary">Back to Lessons</a>
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
        <h3>PACE Numbering System</h3>
        <ul>
            <li><strong>Level 1:</strong> 1013-1024 (3 PACEs per quarter × 4 quarters)</li>
            <li><strong>Level 2:</strong> 1025-1036 (3 PACEs per quarter × 4 quarters)</li>
            <li><strong>Level 3:</strong> 1037-1048 (3 PACEs per quarter × 4 quarters)</li>
            <li>And so on...</li>
        </ul>
        <p><strong>Note:</strong> This will create 3 regular PACE lessons. Monthly and Quarter tests should be created separately.</p>
    </div>
    
    <form method="POST">
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label" for="level">Level *</label>
                <input type="number" class="form-control" id="level" name="level" value="1" min="1" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="quarter">Quarter *</label>
                <select class="form-control" id="quarter" name="quarter" required>
                    <option value="1">Quarter 1</option>
                    <option value="2">Quarter 2</option>
                    <option value="3">Quarter 3</option>
                    <option value="4">Quarter 4</option>
                </select>
            </div>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label" for="start_pace">Start PACE Number *</label>
                <input type="number" class="form-control" id="start_pace" name="start_pace" 
                       placeholder="e.g., 1013 for Level 1 Q1" required>
                <small>First PACE number for this quarter (e.g., 1013, 1016, 1019, 1022 for Level 1)</small>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="end_pace">End PACE Number *</label>
                <input type="number" class="form-control" id="end_pace" name="end_pace" 
                       placeholder="e.g., 1015 for Level 1 Q1" required>
                <small>Last PACE number for this quarter (start + 2, e.g., 1015, 1018, 1021, 1024)</small>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Select Subjects *</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; margin-top: 0.5rem;">
                <?php foreach ($subjects as $subject): ?>
                    <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">
                        <input type="checkbox" name="subjects[]" value="<?php echo $subject['id']; ?>" style="margin-right: 0.5rem;">
                        <?php echo htmlspecialchars($subject['name']); ?> (<?php echo htmlspecialchars($subject['code']); ?>)
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create PACEs
        </button>
    </form>
</div>

<script>
// Auto-calculate end pace based on start pace
document.getElementById('start_pace').addEventListener('input', function() {
    const startPace = parseInt(this.value);
    if (startPace > 0) {
        document.getElementById('end_pace').value = startPace + 2;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
