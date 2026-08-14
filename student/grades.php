<?php
use App\Application\StudentGradeService;

require_once '../config/config.php';
requireRole(['student']);

$pageTitle = 'My Grades';
include '../includes/header.php';

$service = new StudentGradeService();
$data = $service->getGradesForStudent(getCurrentUserId());
$subjects = $data['subjects'];
$overallAverage = $data['overallAverage'];
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
                    $quarterGrades = $subject['quarterGrades'] ?? [];
                    $final = $subject['finalGrade'] ?? null;
                    $q1 = $quarterGrades[1] ?? null;
                    $q2 = $quarterGrades[2] ?? null;
                    $q3 = $quarterGrades[3] ?? null;
                    $q4 = $quarterGrades[4] ?? null;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($subject['name']); ?></strong></td>
                        <td>
                            <?php if ($q1): ?>
                                <?php echo number_format($q1['final_grade'], 2); ?>
                                <br><small style="color: #666;">
                                    (Lessons: <?php echo number_format($q1['lesson_average'], 2); ?>% 
                                    <?php if (!empty($q1['quarter_exam_score'])): ?>
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
                                    <?php if (!empty($q2['quarter_exam_score'])): ?>
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
                                    <?php if (!empty($q4['quarter_exam_score'])): ?>
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
