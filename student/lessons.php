<?php
use App\Application\StudentLessonService;

require_once '../config/config.php';
requireRole(['student']);

$subjectColors = [
    'ENG' => '#e74c3c', // English - Red
    'SCI' => '#3498db', // Science - Blue
    'MATH' => '#f1c40f', // Math - Yellow
    'WB' => '#9b59b6', // Word Building - Purple
    'COMP' => '#e67e22', // Computer - Orange
    'FIL' => '#7f8c8d', // Filipino - Gray
    'AP' => '#8e6b3a', // Araling Panlipunan - Brown
];

$pageTitle = 'My Lessons';
include '../includes/header.php';

$service = new StudentLessonService();
$studentId = getCurrentUserId();
$studentLevel = getCurrentUserLevel();
$selectedQuarter = (int) ($_GET['quarter'] ?? getCurrentQuarter());
$selectedSubject = $_GET['subject'] ?? 'all';
$subjectId = $selectedSubject !== 'all' ? (int) $selectedSubject : null;

$subjects = $service->getSubjects();
$lessons = $service->getFilteredLessons($studentId, $studentLevel, $selectedQuarter, $subjectId);
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">My Lessons</h1>
    </div>
    
    <div style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
        <div class="form-group" style="flex: 1; min-width: 200px;">
            <label class="form-label">Filter by Quarter</label>
            <select class="form-control" onchange="window.location.href='?quarter=' + this.value + '&subject=<?php echo $selectedSubject; ?>'">
                <option value="1" <?php echo $selectedQuarter == 1 ? 'selected' : ''; ?>>Quarter 1</option>
                <option value="2" <?php echo $selectedQuarter == 2 ? 'selected' : ''; ?>>Quarter 2</option>
                <option value="3" <?php echo $selectedQuarter == 3 ? 'selected' : ''; ?>>Quarter 3</option>
                <option value="4" <?php echo $selectedQuarter == 4 ? 'selected' : ''; ?>>Quarter 4</option>
            </select>
        </div>
        
        <div class="form-group" style="flex: 1; min-width: 200px;">
            <label class="form-label">Filter by Subject</label>
            <select class="form-control" onchange="window.location.href='?quarter=<?php echo $selectedQuarter; ?>&subject=' + this.value">
                <option value="all" <?php echo $selectedSubject == 'all' ? 'selected' : ''; ?>>All Subjects</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?php echo $subject['id']; ?>" <?php echo $selectedSubject == $subject['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($subject['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <?php if (empty($lessons)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            No lessons available for this quarter and subject combination.
        </div>
    <?php else: ?>
        <div class="lesson-grid">
            <?php 
            $currentSubject = '';
            $currentTypeSection = null;
            foreach ($lessons as $lesson): 
                if ($currentSubject !== $lesson['subject_name']):
                    $currentSubject = $lesson['subject_name'];
                    $currentTypeSection = null;
            ?>
                <div style="grid-column: 1 / -1; margin-top: 1rem; margin-bottom: 0.5rem;">
                    <h3 style="color: var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem;">
                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($currentSubject); ?>
                    </h3>
                </div>
            <?php endif; ?>

            <?php
            // Add a visible section divider within each subject: PACEs vs tests
            $typeSection = $lesson['pace_type'] ?? 'lesson';
            if ($typeSection !== $currentTypeSection):
                $currentTypeSection = $typeSection;
            ?>
                <div style="grid-column: 1 / -1; margin-top: 0.25rem; margin-bottom: 0.25rem;">
                    <div style="font-weight: 700; color: #333; background: #f1f3f5; padding: 0.5rem 0.75rem; border-radius: 6px;">
                        <?php
                        echo $typeSection === 'lesson'
                            ? 'PACEs'
                            : ($typeSection === 'monthly_test' ? 'Monthly Test' : 'Quarter Test');
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php
            $status = $lesson['status'] ?? 'locked';
            $isLocked = ($status === 'locked' || $status === null);
            $borderColor = $subjectColors[$lesson['subject_code'] ?? ''] ?? '#ccc';
            ?>
            <div class="lesson-card <?php echo $isLocked ? 'locked' : 'unlocked'; ?>" style="border-left: 5px solid <?php echo $borderColor; ?>;">
                <div class="lesson-header">
                    <span class="lesson-number">
                        <?php 
                        // Show PACE number if available, otherwise lesson number
                        echo htmlspecialchars($lesson['pace_number'] ?? $lesson['lesson_number']); 
                        ?>
                        <?php if ($lesson['pace_type'] ?? 'lesson' !== 'lesson'): ?>
                            <span class="badge badge-warning" style="margin-left: 0.5rem;">
                                <?php 
                                $paceType = $lesson['pace_type'] ?? 'lesson';
                                echo $paceType === 'monthly_test' ? 'Monthly Test' : 
                                    ($paceType === 'quarter_test' ? 'Quarter Test' : ''); 
                                ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <span class="badge <?php 
                        echo $status === 'completed' ? 'badge-success' : 
                            ($status === 'in_progress' ? 'badge-warning' : 
                            ($status === 'unlocked' ? 'badge-info' : 'badge-secondary')); 
                    ?>">
                        <?php echo ucfirst($status ?? 'Locked'); ?>
                    </span>
                </div>
                <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                <div class="lesson-description">
                    <?php echo htmlspecialchars(substr($lesson['description'] ?? 'No description available', 0, 150)); ?>
                    <?php if (strlen($lesson['description'] ?? '') > 150) echo '...'; ?>
                </div>
                <div class="lesson-footer">
                    <?php if (!$isLocked): ?>
                        <a href="lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-play"></i> Study
                        </a>
                    <?php else: ?>
                        <span class="btn btn-secondary btn-sm" disabled>
                            <i class="fas fa-lock"></i> Locked
                        </span>
                    <?php endif; ?>
                    <?php if ($lesson['has_score'] > 0): ?>
                        <a href="quiz-result.php?lesson_id=<?php echo $lesson['id']; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-chart-line"></i> View Score
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
