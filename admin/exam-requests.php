<?php
require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();

// Handle approve/deny action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $requestId = $_GET['id'];
    $remarks = $_POST['remarks'] ?? '';
    $adminId = getCurrentUserId();
    
    // Get request
    $stmt = $conn->prepare("SELECT * FROM exam_requests WHERE id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($request) {
        $status = $action === 'approve' ? 'approved' : 'denied';
        
        $stmt = $conn->prepare("
            UPDATE exam_requests 
            SET status = ?, remarks = ?, reviewed_at = NOW(), reviewed_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssii", $status, $remarks, $adminId, $requestId);
        $stmt->execute();
        $stmt->close();
        
        // If approved, unlock the lesson for the student
        if ($status === 'approved') {
            $stmt = $conn->prepare("
                INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                VALUES (?, ?, 'unlocked', NOW())
                ON DUPLICATE KEY UPDATE status = 'unlocked', unlocked_at = NOW()
            ");
            $stmt->bind_param("ii", $request['student_id'], $request['lesson_id']);
            $stmt->execute();
            $stmt->close();
        }
        
        header('Location: exam-requests.php?message=' . urlencode('Request ' . $status . ' successfully'));
        exit();
    }
}

$filter = $_GET['filter'] ?? 'all';
$query = "
    SELECT er.*, 
           u.name as student_name, u.level as student_level,
           l.lesson_number, l.title as lesson_title,
           s.name as subject_name, s.code as subject_code,
           reviewer.name as reviewer_name
    FROM exam_requests er
    INNER JOIN users u ON er.student_id = u.id
    INNER JOIN lessons l ON er.lesson_id = l.id
    INNER JOIN subjects s ON l.subject_id = s.id
    LEFT JOIN users reviewer ON er.reviewed_by = reviewer.id
    WHERE 1=1
";

if ($filter === 'pending') {
    $query .= " AND er.status = 'pending'";
} elseif ($filter === 'approved') {
    $query .= " AND er.status = 'approved'";
} elseif ($filter === 'denied') {
    $query .= " AND er.status = 'denied'";
}

$query .= " ORDER BY er.requested_at DESC";

$requests = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get counts
$totalRequests = count($requests);
$pendingCount = $conn->query("SELECT COUNT(*) as total FROM exam_requests WHERE status = 'pending'")->fetch_assoc()['total'];
$approvedCount = $conn->query("SELECT COUNT(*) as total FROM exam_requests WHERE status = 'approved'")->fetch_assoc()['total'];
$deniedCount = $conn->query("SELECT COUNT(*) as total FROM exam_requests WHERE status = 'denied'")->fetch_assoc()['total'];

closeDBConnection($conn);

$pageTitle = 'Exam Requests';
include '../includes/header.php';

$message = $_GET['message'] ?? '';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Exam Requests</h1>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div style="margin-bottom: 1.5rem;">
        <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">
            All (<?php echo $totalRequests; ?>)
        </a>
        <a href="?filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">
            Pending (<?php echo $pendingCount; ?>)
        </a>
        <a href="?filter=approved" class="btn <?php echo $filter === 'approved' ? 'btn-primary' : 'btn-secondary'; ?>">
            Approved (<?php echo $approvedCount; ?>)
        </a>
        <a href="?filter=denied" class="btn <?php echo $filter === 'denied' ? 'btn-primary' : 'btn-secondary'; ?>">
            Denied (<?php echo $deniedCount; ?>)
        </a>
    </div>
    
    <?php if (!empty($requests)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Lesson</th>
                        <th>Subject</th>
                        <th>Request Type</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Reviewed By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($request['student_name']); ?></strong><br>
                                <small>Level <?php echo $request['student_level']; ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($request['lesson_number']); ?></strong><br>
                                <small><?php echo htmlspecialchars($request['lesson_title']); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo htmlspecialchars($request['subject_name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?>
                                <?php if ($request['quarter']): ?>
                                    <br><small>Quarter <?php echo $request['quarter']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = 'badge-secondary';
                                if ($request['status'] === 'approved') $statusClass = 'badge-success';
                                elseif ($request['status'] === 'denied') $statusClass = 'badge-danger';
                                elseif ($request['status'] === 'pending') $statusClass = 'badge-warning';
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('M j, Y g:i A', strtotime($request['requested_at'])); ?>
                            </td>
                            <td>
                                <?php echo $request['reviewer_name'] ? htmlspecialchars($request['reviewer_name']) : '-'; ?>
                            </td>
                            <td>
                                <?php if ($request['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline-block;" action="?action=approve&id=<?php echo $request['id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this exam request?')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline-block;" action="?action=deny&id=<?php echo $request['id']; ?>">
                                        <div style="display: inline-block;">
                                            <input type="text" name="remarks" placeholder="Remarks (optional)" class="form-control" style="display: inline-block; width: 150px; margin-right: 5px;">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Deny this exam request?')">
                                                <i class="fas fa-times"></i> Deny
                                            </button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <?php if ($request['remarks']): ?>
                                        <small><strong>Remarks:</strong> <?php echo htmlspecialchars($request['remarks']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No exam requests found.
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
