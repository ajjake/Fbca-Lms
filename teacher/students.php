<?php
require_once '../config/config.php';
requireRole(['teacher']);

$pageTitle = 'Assigned Students';
include '../includes/header.php';

$conn = getDBConnection();
$teacherId = getCurrentUserId();

// Get assigned levels
$stmt = $conn->prepare("SELECT level FROM teacher_levels WHERE teacher_id = ?");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$lvRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$levels = array_column($lvRows, 'level');

// Get assigned subjects (for optional filtering)
$stmt = $conn->prepare("SELECT subject_id FROM teacher_subjects WHERE teacher_id = ?");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$subRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$subjectIds = array_column($subRows, 'subject_id');

// Build student query restricted by assigned levels
$students = [];
if (!empty($levels)) {
    $placeholders = implode(',', array_fill(0, count($levels), '?'));
    $types = str_repeat('i', count($levels));
    $sql = "SELECT id, name, username, level, email, created_at FROM users WHERE role = 'student' AND level IN ($placeholders) ORDER BY name";
    $pstmt = $conn->prepare($sql);
    // bind params dynamic
    $bind_names[] = $types;
    for ($i = 0; $i < count($levels); $i++) {
        $bind_name = 'b' . $i;
        $$bind_name = $levels[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array(array($pstmt, 'bind_param'), $bind_names);
    $pstmt->execute();
    $students = $pstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pstmt->close();
}

// For each student, compute progress counts (limited to teacher subjects)
$studentStats = [];
foreach ($students as $s) {
    $sid = $s['id'];
    // Count completed/unlocked for lessons that belong to teacher's subjects
    $completed = 0;
    $unlocked = 0;
    if (!empty($subjectIds)) {
        $placeSub = implode(',', array_fill(0, count($subjectIds), '?'));
        $typesSub = str_repeat('i', count($subjectIds));
        $sql = "SELECT SUM(CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END) as completed,
                       SUM(CASE WHEN sp.status IN ('unlocked','in_progress','completed') THEN 1 ELSE 0 END) as unlocked
                FROM student_progress sp
                INNER JOIN lessons l ON sp.lesson_id = l.id
                WHERE sp.student_id = ? AND l.subject_id IN ($placeSub)";
        $stmt = $conn->prepare($sql);
        // bind: first student id, then subject ids
        $bind = array_merge(array('i'), array($sid));
        $typesAll = 'i' . $typesSub;
        $bind_names = array($typesAll, &$sid);
        for ($i=0;$i<count($subjectIds);$i++){
            $varName = 's' . $i;
            $$varName = $subjectIds[$i];
            $bind_names[] = &$$varName;
        }
        call_user_func_array(array($stmt,'bind_param'), $bind_names);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $completed = (int)($res['completed'] ?? 0);
        $unlocked = (int)($res['unlocked'] ?? 0);
        $stmt->close();
    }

    $studentStats[$sid] = [
        'completed' => $completed,
        'unlocked' => $unlocked
    ];
}

closeDBConnection($conn);
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Assigned Students</h1>
    </div>

    <?php if (empty($levels)): ?>
        <div class="alert alert-warning">You are not assigned any levels. Assign levels in the admin panel to see students.</div>
    <?php else: ?>
        <?php if (empty($students)): ?>
            <div class="alert alert-info">No students found for your assigned levels.</div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Level</th>
                            <th>Completed Lessons</th>
                            <th>Unlocked Lessons</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $st): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($st['name']); ?></td>
                                <td><?php echo htmlspecialchars($st['username']); ?></td>
                                <td>Level <?php echo htmlspecialchars($st['level']); ?></td>
                                <td><?php echo $studentStats[$st['id']]['completed'] ?? 0; ?></td>
                                <td><?php echo $studentStats[$st['id']]['unlocked'] ?? 0; ?></td>
                                <td>
                                    <a href="student-profile.php?student_id=<?php echo $st['id']; ?>" class="btn btn-sm btn-primary">View Profile</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>