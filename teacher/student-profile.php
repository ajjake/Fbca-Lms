<?php
require_once '../config/config.php';
requireRole(['teacher']);

$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if (!$studentId) {
    header('Location: students.php');
    exit();
}

$conn = getDBConnection();
$teacherId = getCurrentUserId();

// Get student basic info
$cols = [];
$checkAvatar = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($checkAvatar && $checkAvatar->num_rows > 0) $cols[] = 'avatar';
$checkLrn = $conn->query("SHOW COLUMNS FROM users LIKE 'lrn'");
if ($checkLrn && $checkLrn->num_rows > 0) $cols[] = 'lrn';
$checkGName = $conn->query("SHOW COLUMNS FROM users LIKE 'guardian_name'");
if ($checkGName && $checkGName->num_rows > 0) $cols[] = 'guardian_name';
$checkGContact = $conn->query("SHOW COLUMNS FROM users LIKE 'guardian_contact'");
if ($checkGContact && $checkGContact->num_rows > 0) $cols[] = 'guardian_contact';

$selectFields = 'id, username, name, email, level, created_at' . (!empty($cols) ? ', ' . implode(', ', $cols) : '');
$stmt = $conn->prepare("SELECT $selectFields FROM users WHERE id = ? AND role = 'student'");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    closeDBConnection($conn);
    header('Location: students.php');
    exit();
}

// Check teacher has this level assigned
$stmt = $conn->prepare("SELECT level FROM teacher_levels WHERE teacher_id = ?");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$lvRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$assignedLevels = array_column($lvRows, 'level');
if (!empty($assignedLevels) && !in_array((int)$student['level'], $assignedLevels)) {
    closeDBConnection($conn);
    include '../includes/header.php';
    echo '<div class="card"><div class="card-header"><h1 class="card-title">Access Denied</h1></div><div class="card-body">You are not assigned to this student\'s level.</div></div>'; 
    include '../includes/footer.php';
    exit();
}

// Get teacher's subjects
$stmt = $conn->prepare("SELECT ts.subject_id, s.name, s.code FROM teacher_subjects ts JOIN subjects s ON ts.subject_id = s.id WHERE ts.teacher_id = ?");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$subRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$subjectIds = array_column($subRows, 'subject_id');

// Student progress summary for teacher's subjects
$progress = ['completed' => 0, 'unlocked' => 0];
if (!empty($subjectIds)) {
    $place = implode(',', array_fill(0, count($subjectIds), '?'));
    $types = str_repeat('i', count($subjectIds));
    $sql = "SELECT SUM(CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END) as completed,
                   SUM(CASE WHEN sp.status IN ('unlocked','in_progress','completed') THEN 1 ELSE 0 END) as unlocked
            FROM student_progress sp
            JOIN lessons l ON sp.lesson_id = l.id
            WHERE sp.student_id = ? AND l.subject_id IN ($place)";
    $stmt = $conn->prepare($sql);
    $bind_names = array_merge(array('i' . $types, &$studentId), array());
    for ($i = 0; $i < count($subjectIds); $i++) {
        $var = 's' . $i;
        $$var = $subjectIds[$i];
        $bind_names[] = &$$var;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $progress['completed'] = (int)($res['completed'] ?? 0);
    $progress['unlocked'] = (int)($res['unlocked'] ?? 0);
    $stmt->close();
}

// Get quarter grades for this student in teacher subjects
$quarterGrades = [];
if (!empty($subjectIds)) {
    $place = implode(',', array_fill(0, count($subjectIds), '?'));
    $types = str_repeat('i', count($subjectIds));
    $sql = "SELECT q.*, s.name as subject_name FROM quarter_grades q JOIN subjects s ON q.subject_id = s.id WHERE q.student_id = ? AND q.subject_id IN ($place) ORDER BY s.name, q.quarter";
    $stmt = $conn->prepare($sql);
    $bind_names = array_merge(array('i' . $types, &$studentId), array());
    for ($i = 0; $i < count($subjectIds); $i++) {
        $var = 'a' . $i;
        $$var = $subjectIds[$i];
        $bind_names[] = &$$var;
    }
    call_user_func_array(array($stmt,'bind_param'), $bind_names);
    $stmt->execute();
    $quarterGrades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get final grades for this student in teacher subjects
$finalGrades = [];
if (!empty($subjectIds)) {
    $place = implode(',', array_fill(0, count($subjectIds), '?'));
    $types = str_repeat('i', count($subjectIds));
    $sql = "SELECT f.*, s.name as subject_name FROM final_grades f JOIN subjects s ON f.subject_id = s.id WHERE f.student_id = ? AND f.subject_id IN ($place) ORDER BY s.name";
    $stmt = $conn->prepare($sql);
    $bind_names = array_merge(array('i' . $types, &$studentId), array());
    for ($i = 0; $i < count($subjectIds); $i++) {
        $var = 'b' . $i;
        $$var = $subjectIds[$i];
        $bind_names[] = &$$var;
    }
    call_user_func_array(array($stmt,'bind_param'), $bind_names);
    $stmt->execute();
    $finalGrades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Lesson scores (recent)
$recentScores = [];
if (!empty($subjectIds)) {
    $place = implode(',', array_fill(0, count($subjectIds), '?'));
    $types = str_repeat('i', count($subjectIds));
    $sql = "SELECT ls.*, l.title as lesson_title, s.name as subject_name FROM lesson_scores ls JOIN lessons l ON ls.lesson_id = l.id JOIN subjects s ON l.subject_id = s.id WHERE ls.student_id = ? AND l.subject_id IN ($place) ORDER BY ls.taken_at DESC LIMIT 12";
    $stmt = $conn->prepare($sql);
    $bind_names = array_merge(array('i' . $types, &$studentId), array());
    for ($i = 0; $i < count($subjectIds); $i++) {
        $var = 'c' . $i;
        $$var = $subjectIds[$i];
        $bind_names[] = &$$var;
    }
    call_user_func_array(array($stmt,'bind_param'), $bind_names);
    $stmt->execute();
    $recentScores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

closeDBConnection($conn);

$pageTitle = 'Student Profile: ' . htmlspecialchars($student['name']);
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div style="display:flex; align-items:center; gap:12px;">
            <?php if (!empty($student['avatar'])): ?>
                <img src="<?php echo htmlspecialchars((defined('BASE_URL') ? BASE_URL : '/') . $student['avatar']); ?>" alt="avatar" style="width:96px; height:96px; object-fit:cover; border-radius:8px; border:1px solid #ddd;">
            <?php else: ?>
                <div style="width:96px; height:96px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid #ddd; font-weight:600; color:#666;">
                    <?php echo strtoupper(substr($student['name'],0,1)); ?>
                </div>
            <?php endif; ?>
            <h1 class="card-title"><?php echo htmlspecialchars($student['name']); ?> — Profile</h1>
        </div>
        <a href="students.php" class="btn btn-secondary">Back</a>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <h3>Profile</h3>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($student['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
            <p><strong>Level:</strong> <?php echo (int)$student['level']; ?></p>
            <?php if (!empty($student['lrn'])): ?>
                <p><strong>LRN:</strong> <?php echo htmlspecialchars($student['lrn']); ?></p>
            <?php endif; ?>
            <?php if (!empty($student['guardian_name']) || !empty($student['guardian_contact'])): ?>
                <p><strong>Guardian:</strong>
                    <?php echo htmlspecialchars($student['guardian_name'] ?? ''); ?>
                    <?php if (!empty($student['guardian_contact'])): ?> — <?php echo htmlspecialchars($student['guardian_contact']); ?><?php endif; ?>
                </p>
            <?php endif; ?>
            <p><strong>Joined:</strong> <?php echo date('M j, Y', strtotime($student['created_at'])); ?></p>
            <p><strong>Progress (your subjects):</strong></p>
            <ul>
                <li>Completed: <?php echo $progress['completed']; ?></li>
                <li>Unlocked/In progress: <?php echo $progress['unlocked']; ?></li>
            </ul>
        </div>

        <div class="card">
            <h3>Actions</h3>
            <?php if (!empty($subRows)): ?>
                <?php foreach ($subRows as $s): ?>
                    <div style="margin-bottom:0.5rem;">
                        <strong><?php echo htmlspecialchars($s['name']); ?></strong>
                        <div style="margin-top:0.4rem;">
                            <a class="btn btn-primary btn-sm" href="lesson-add.php?subject_id=<?php echo $s['subject_id']; ?>&level=<?php echo (int)$student['level']; ?>">Create Lesson for this Subject</a>
                            <a class="btn btn-success btn-sm" href="quizzes.php?subject_id=<?php echo $s['subject_id']; ?>">Manage Quizzes</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">You are not assigned to any subjects for this student.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Quarter Grades (Your Subjects)</h2></div>
    <?php if (empty($quarterGrades)): ?>
        <div class="alert alert-info">No quarter grades available for this student in your subjects.</div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Quarter</th>
                        <th>Lesson Avg</th>
                        <th>Quarter Exam</th>
                        <th>Final Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quarterGrades as $q): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($q['subject_name']); ?></td>
                            <td>Q<?php echo (int)$q['quarter']; ?></td>
                            <td><?php echo number_format($q['lesson_average'],2); ?>%</td>
                            <td><?php echo number_format($q['quarter_exam_score'],2); ?>%</td>
                            <td><?php echo number_format($q['final_grade'],2); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Final Grades (Your Subjects)</h2></div>
    <?php if (empty($finalGrades)): ?>
        <div class="alert alert-info">No final grades available for this student in your subjects.</div>
    <?php else: ?>
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
                    <?php foreach ($finalGrades as $f): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f['subject_name']); ?></td>
                            <td><?php echo number_format($f['q1_grade'],2); ?>%</td>
                            <td><?php echo number_format($f['q2_grade'],2); ?>%</td>
                            <td><?php echo number_format($f['q3_grade'],2); ?>%</td>
                            <td><?php echo number_format($f['q4_grade'],2); ?>%</td>
                            <td><?php echo number_format($f['final_average'],2); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Recent Scores</h2></div>
    <?php if (empty($recentScores)): ?>
        <div class="alert alert-info">No recent scores for this student in your subjects.</div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Subject</th>
                        <th>Lesson</th>
                        <th>Score</th>
                        <th>Percent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentScores as $rs): ?>
                        <tr>
                            <td><?php echo date('M j, Y g:i A', strtotime($rs['taken_at'])); ?></td>
                            <td><?php echo htmlspecialchars($rs['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($rs['lesson_title']); ?></td>
                            <td><?php echo number_format($rs['score'],2); ?></td>
                            <td><?php echo number_format($rs['percentage'],2); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
