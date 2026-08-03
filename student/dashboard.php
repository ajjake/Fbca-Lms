<?php
require_once '../config/config.php';
requireRole(['student']);

$pageTitle = 'Student Dashboard';
include '../includes/header.php';

$conn = getDBConnection();
$studentId = getCurrentUserId();
$studentLevel = getCurrentUserLevel();
$currentQuarter = getCurrentQuarter();

$cols = [];
$checkAvatar = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($checkAvatar && $checkAvatar->num_rows > 0) $cols[] = 'avatar';
$checkLrn = $conn->query("SHOW COLUMNS FROM users LIKE 'lrn'");
if ($checkLrn && $checkLrn->num_rows > 0) $cols[] = 'lrn';
$checkGName = $conn->query("SHOW COLUMNS FROM users LIKE 'guardian_name'");
if ($checkGName && $checkGName->num_rows > 0) $cols[] = 'guardian_name';
$checkGContact = $conn->query("SHOW COLUMNS FROM users LIKE 'guardian_contact'");
if ($checkGContact && $checkGContact->num_rows > 0) $cols[] = 'guardian_contact';

$select = 'name, level' . (!empty($cols) ? ', ' . implode(', ', $cols) : '');
$stmt = $conn->prepare("SELECT $select FROM users WHERE id = ?");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get subjects count
$stmt = $conn->prepare("SELECT COUNT(DISTINCT subject_id) as total FROM lessons WHERE level = ?");
$stmt->bind_param("i", $studentLevel);
$stmt->execute();
$subjectsCount = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get completed lessons count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM student_progress WHERE student_id = ? AND status = 'completed'");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$completedLessons = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get unlocked lessons count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM student_progress WHERE student_id = ? AND status IN ('unlocked', 'in_progress', 'completed')");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$unlockedLessons = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get pending exam requests
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM exam_requests WHERE student_id = ? AND status = 'pending'");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$pendingRequests = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get final average grade
$stmt = $conn->prepare("SELECT AVG(final_average) as avg_grade FROM final_grades WHERE student_id = ?");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$avgGrade = $result->num_rows > 0 ? $result->fetch_assoc()['avg_grade'] : 0;
$stmt->close();

// Get recent lessons
$stmt = $conn->prepare("
    SELECT l.*, s.name as subject_name, sp.status 
    FROM lessons l
    INNER JOIN subjects s ON l.subject_id = s.id
    LEFT JOIN student_progress sp ON l.id = sp.lesson_id AND sp.student_id = ?
    WHERE l.level = ? AND l.quarter = ?
    ORDER BY s.name, l.order_index
    LIMIT 6
");
$stmt->bind_param("iii", $studentId, $studentLevel, $currentQuarter);
$stmt->execute();
$recentLessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);
?>

<div class="card">
    <div class="card-header" style="display:flex; align-items:center; gap:12px;">
        <?php if (!empty($student['avatar'])): ?>
            <img src="<?php echo htmlspecialchars((defined('BASE_URL') ? BASE_URL : '/') . $student['avatar']); ?>" alt="avatar" style="width:96px; height:96px; object-fit:cover; border-radius:8px; border:1px solid #ddd;">
        <?php else: ?>
            <div style="width:96px; height:96px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid #ddd; font-weight:600; color:#666;">
                <?php echo strtoupper(substr($student['name'],0,1)); ?>
            </div>
        <?php endif; ?>
        <div>
            <h1 class="card-title">Welcome, <?php echo htmlspecialchars($student['name']); ?>!</h1>
            <?php if (!empty($student['lrn']) || !empty($student['guardian_name']) || !empty($student['guardian_contact'])): ?>
                <div style="font-size:0.95rem; color:#555; margin-top:4px;">
                    <?php if (!empty($student['lrn'])): ?><span><strong>LRN:</strong> <?php echo htmlspecialchars($student['lrn']); ?></span><?php endif; ?>
                    <?php if (!empty($student['guardian_name'])): ?>
                        <span style="margin-left:8px;"><strong>Guardian:</strong> <?php echo htmlspecialchars($student['guardian_name']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($student['guardian_contact'])): ?>
                        <span style="margin-left:8px;"><strong>Contact:</strong> <?php echo htmlspecialchars($student['guardian_contact']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Current Level</div>
            <div class="stat-value"><?php echo $student['level']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Current Quarter</div>
            <div class="stat-value">Q<?php echo $currentQuarter; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Subjects Available</div>
            <div class="stat-value"><?php echo $subjectsCount; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Completed Lessons</div>
            <div class="stat-value"><?php echo $completedLessons; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Unlocked Lessons</div>
            <div class="stat-value"><?php echo $unlockedLessons; ?></div>
        </div>
        <?php
        // Final average is restricted: only admins can see it by default.
        // Students may request to view their final average per quarter; pending admin approval.
        $showFinalAverage = false;
        if (isAdmin()) {
            $showFinalAverage = true;
        } else {
            // Check if student has an approved or pending final average request for the current quarter
            $requestStatus = null;
            $conn2 = getDBConnection();
            // Only query if table exists
            $check = $conn2->query("SHOW TABLES LIKE 'final_average_requests'");
            if ($check && $check->num_rows > 0) {
                $stmt2 = $conn2->prepare("SELECT status FROM final_average_requests WHERE student_id = ? AND quarter = ? ORDER BY requested_at DESC LIMIT 1");
                $stmt2->bind_param("ii", $studentId, $currentQuarter);
                if ($stmt2->execute()) {
                    $res2 = $stmt2->get_result()->fetch_assoc();
                    if ($res2) {
                        $requestStatus = $res2['status'];
                        if ($requestStatus === 'approved') {
                            $showFinalAverage = true;
                        }
                    }
                }
                $stmt2->close();
            }
            closeDBConnection($conn2);
        }

        if ($showFinalAverage): ?>
            <div class="stat-card">
                <div class="stat-label">Final Average</div>
                <div class="stat-value"><?php echo number_format($avgGrade, 2); ?>%</div>
            </div>
        <?php else: ?>
            <div class="stat-card">
                <div class="stat-label">Final Average (Restricted)</div>
                <div class="stat-value">—</div>
            </div>
        <?php endif; ?>
    </div>
</div>

    <?php if (!isset($showFinalAverage) || !$showFinalAverage): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Final Average Request</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($requestStatus) && $requestStatus === 'pending'): ?>
                    <div class="alert alert-warning"><i class="fas fa-clock"></i> You have a pending request for Final Average for Quarter <?php echo $currentQuarter; ?>. Please wait for admin approval.</div>
                <?php elseif (!empty($requestStatus) && $requestStatus === 'denied'): ?>
                    <div class="alert alert-danger"><i class="fas fa-times-circle"></i> Your previous request for Quarter <?php echo $currentQuarter; ?> was denied. You may submit a new request.</div>
                    <button id="request-final-btn" class="btn btn-primary">Request Final Average for Quarter <?php echo $currentQuarter; ?></button>
                <?php else: ?>
                    <p>The final average per quarter is restricted. You can request your final average for Quarter <?php echo $currentQuarter; ?> and an admin will review your request.</p>
                    <button id="request-final-btn" class="btn btn-primary">Request Final Average for Quarter <?php echo $currentQuarter; ?></button>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var btn = document.getElementById('request-final-btn');
            if (!btn) return;
            btn.addEventListener('click', function() {
                if (!confirm('Submit request to view your final average for Quarter <?php echo $currentQuarter; ?>? Admin will review payment status before approval.')) return;
                btn.disabled = true;
                fetch('<?php echo BASE_URL; ?>api/request-final-average.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ quarter: <?php echo $currentQuarter; ?> })
                }).then(function(res){ return res.json(); }).then(function(data){
                    alert(data.message || 'Request submitted');
                    if (data.success) location.reload(); else btn.disabled = false;
                }).catch(function(err){ alert('Request failed'); btn.disabled = false; });
            });
        });
        </script>
    <?php endif; ?>

<?php if ($pendingRequests > 0): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        You have <?php echo $pendingRequests; ?> pending exam request(s). Please wait for teacher approval.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Lessons - Quarter <?php echo $currentQuarter; ?></h2>
        <a href="lessons.php" class="btn btn-primary">View All Lessons</a>
    </div>
    
    <div class="lesson-grid">
        <?php foreach ($recentLessons as $lesson): ?>
            <?php
            $status = $lesson['status'] ?? 'locked';
            $isLocked = ($status === 'locked' || $status === null);
            ?>
            <div class="lesson-card <?php echo $isLocked ? 'locked' : 'unlocked'; ?>">
                <div class="lesson-header">
                    <span class="lesson-number">
                        <?php echo htmlspecialchars($lesson['pace_number'] ?? $lesson['lesson_number']); ?>
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
                    <strong>Subject:</strong> <?php echo htmlspecialchars($lesson['subject_name']); ?><br>
                    <?php echo htmlspecialchars(substr($lesson['description'] ?? 'No description', 0, 100)); ?>...
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
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
