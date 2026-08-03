<?php
require_once '../config/config.php';
requireRole(['student']);

$pageTitle = 'My Grades';
include '../includes/header.php';

$conn = getDBConnection();
$studentId = getCurrentUserId();

// Get all subjects
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get final grades for all subjects
$finalGrades = [];
foreach ($subjects as $subject) {
    $stmt = $conn->prepare("SELECT * FROM final_grades WHERE student_id = ? AND subject_id = ?");
    $stmt->bind_param("ii", $studentId, $subject['id']);
    $stmt->execute();
    $grade = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($grade) {
        $finalGrades[$subject['id']] = $grade;
    }
}

// Get quarter grades
$quarterGrades = [];
foreach ($subjects as $subject) {
    for ($q = 1; $q <= 4; $q++) {
        $stmt = $conn->prepare("SELECT * FROM quarter_grades WHERE student_id = ? AND subject_id = ? AND quarter = ?");
        $stmt->bind_param("iii", $studentId, $subject['id'], $q);
        $stmt->execute();
        $grade = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($grade) {
            $quarterGrades[$subject['id']][$q] = $grade;
        }
    }
}

closeDBConnection($conn);
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">My Grades</h1>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Q1</th>
                    <th>Q2</th>
                    <th>Q3</th>
                    <th>Q4</th>
                    <th>Final Average</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $subject): ?>
                    <?php
                    $final = $finalGrades[$subject['id']] ?? null;
                    $q1 = $quarterGrades[$subject['id']][1] ?? null;
                    $q2 = $quarterGrades[$subject['id']][2] ?? null;
                    $q3 = $quarterGrades[$subject['id']][3] ?? null;
                    $q4 = $quarterGrades[$subject['id']][4] ?? null;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($subject['name']); ?></strong></td>
                        <td>
                            <?php if ($q1): ?>
                                <?php echo number_format($q1['final_grade'], 2); ?>
                                <br><small style="color: #666;">
                                    (Lessons: <?php echo number_format($q1['lesson_average'], 2); ?>% 
                                    <?php if ($q1['quarter_exam_score'] > 0): ?>
                                        | Exam: <?php echo number_format($q1['quarter_exam_score'], 2); ?>%
                                    <?php endif; ?>)
                                </small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($q2): ?>
                                <?php echo number_format($q2['final_grade'], 2); ?>
                                <br><small style="color: #666;">
                                    (Lessons: <?php echo number_format($q2['lesson_average'], 2); ?>% 
                                    <?php if ($q2['quarter_exam_score'] > 0): ?>
                                        | Exam: <?php echo number_format($q2['quarter_exam_score'], 2); ?>%
                                    <?php endif; ?>)
                                </small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($q3): ?>
                                <?php echo number_format($q3['final_grade'], 2); ?>
                                <br><small style="color: #666;">
                                    (Lessons: <?php echo number_format($q3['lesson_average'], 2); ?>% 
                                    <?php if ($q3['quarter_exam_score'] > 0): ?>
                                        | Exam: <?php echo number_format($q3['quarter_exam_score'], 2); ?>%
                                    <?php endif; ?>)
                                </small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($q4): ?>
                                <?php echo number_format($q4['final_grade'], 2); ?>
                                <br><small style="color: #666;">
                                    (Lessons: <?php echo number_format($q4['lesson_average'], 2); ?>% 
                                    <?php if ($q4['quarter_exam_score'] > 0): ?>
                                        | Exam: <?php echo number_format($q4['quarter_exam_score'], 2); ?>%
                                    <?php endif; ?>)
                                </small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="font-size: 1.1rem; color: var(--primary-color);">
                                <?php echo $final ? number_format($final['final_average'], 2) : '-'; ?>
                            </strong>
                            <?php if ($final): ?>
                                <br><small style="color: #666;">
                                    (Avg of Q1-Q4)
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Overall Final Average</h2>
    </div>
    
    <?php
    $overallAverage = 0;
    $count = 0;
    foreach ($finalGrades as $grade) {
        if ($grade['final_average'] > 0) {
            $overallAverage += $grade['final_average'];
            $count++;
        }
    }
    $overallAverage = $count > 0 ? $overallAverage / $count : 0;
    ?>
    
    <div style="text-align: center; padding: 2rem;">
        <div style="font-size: 3rem; font-weight: bold; color: var(--primary-color); margin: 1rem 0;">
            <?php echo number_format($overallAverage, 2); ?>%
        </div>
        <div class="progress" style="max-width: 500px; margin: 0 auto;">
            <div class="progress-bar" style="width: <?php echo min($overallAverage, 100); ?>%;">
                <?php echo number_format($overallAverage, 1); ?>%
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
