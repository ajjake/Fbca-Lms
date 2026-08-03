<?php
require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();

// Handle approve/deny
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $reqId = (int)$_GET['id'];
    $remarks = $_POST['remarks'] ?? '';
    $adminId = getCurrentUserId();

    $stmt = $conn->prepare("SELECT * FROM final_average_requests WHERE id = ?");
    $stmt->bind_param("i", $reqId);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($req) {
        $status = $action === 'approve' ? 'approved' : 'denied';
        $stmt = $conn->prepare("UPDATE final_average_requests SET status = ?, remarks = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->bind_param("ssii", $status, $remarks, $adminId, $reqId);
        $stmt->execute();
        $stmt->close();

        header('Location: final-requests.php?message=' . urlencode('Request ' . $status . ' successfully'));
        exit();
    }
}

$filter = $_GET['filter'] ?? 'all';
$query = "SELECT far.*, u.name as student_name, u.level as student_level, reviewer.name as reviewer_name
          FROM final_average_requests far
          INNER JOIN users u ON far.student_id = u.id
          LEFT JOIN users reviewer ON far.reviewed_by = reviewer.id
          WHERE 1=1";

if ($filter === 'pending') $query .= " AND far.status = 'pending'";
elseif ($filter === 'approved') $query .= " AND far.status = 'approved'";
elseif ($filter === 'denied') $query .= " AND far.status = 'denied'";

$query .= " ORDER BY far.requested_at DESC";

// Ensure the final_average_requests table exists before querying
$requests = [];
$total = 0;
$pendingCount = 0;
$approvedCount = 0;
$deniedCount = 0;
$check = $conn->query("SHOW TABLES LIKE 'final_average_requests'");
if ($check && $check->num_rows > 0) {
    $res = $conn->query($query);
    if ($res !== false) {
        $requests = $res->fetch_all(MYSQLI_ASSOC);
    } else {
        $requests = [];
    }

    // counts (safe queries)
    $pendingCount = (int)($conn->query("SELECT COUNT(*) as total FROM final_average_requests WHERE status = 'pending'")->fetch_assoc()['total'] ?? 0);
    $approvedCount = (int)($conn->query("SELECT COUNT(*) as total FROM final_average_requests WHERE status = 'approved'")->fetch_assoc()['total'] ?? 0);
    $deniedCount = (int)($conn->query("SELECT COUNT(*) as total FROM final_average_requests WHERE status = 'denied'")->fetch_assoc()['total'] ?? 0);
    $total = count($requests);
}

closeDBConnection($conn);

$pageTitle = 'Final Average Requests';
include '../includes/header.php';

$message = $_GET['message'] ?? '';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Final Average Requests</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div style="margin-bottom: 1.5rem;">
        <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">All (<?php echo $total; ?>)</a>
        <a href="?filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">Pending (<?php echo $pendingCount; ?>)</a>
        <a href="?filter=approved" class="btn <?php echo $filter === 'approved' ? 'btn-primary' : 'btn-secondary'; ?>">Approved (<?php echo $approvedCount; ?>)</a>
        <a href="?filter=denied" class="btn <?php echo $filter === 'denied' ? 'btn-primary' : 'btn-secondary'; ?>">Denied (<?php echo $deniedCount; ?>)</a>
    </div>

    <?php if (!empty($requests)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Quarter</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Reviewed By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['student_name']); ?></strong><br><small>Level <?php echo $r['student_level']; ?></small></td>
                            <td>Q<?php echo $r['quarter']; ?></td>
                            <td>
                                <?php $cls='badge-secondary'; if ($r['status']=='approved') $cls='badge-success'; elseif ($r['status']=='denied') $cls='badge-danger'; elseif ($r['status']=='pending') $cls='badge-warning'; ?>
                                <span class="badge <?php echo $cls; ?>"><?php echo ucfirst($r['status']); ?></span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($r['requested_at'])); ?></td>
                            <td><?php echo $r['reviewer_name'] ? htmlspecialchars($r['reviewer_name']) : '-'; ?></td>
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <form method="POST" action="?action=approve&id=<?php echo $r['id']; ?>" style="display:inline-block;">
                                        <button class="btn btn-success btn-sm" type="submit" onclick="return confirm('Approve this request?')"><i class="fas fa-check"></i> Approve</button>
                                    </form>
                                    <form method="POST" action="?action=deny&id=<?php echo $r['id']; ?>" style="display:inline-block; margin-left:5px;">
                                        <input type="text" name="remarks" placeholder="Remarks (optional)" class="form-control" style="display:inline-block; width:150px;">
                                        <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Deny this request?')" style="margin-left:5px;"><i class="fas fa-times"></i> Deny</button>
                                    </form>
                                <?php else: ?>
                                    <?php if ($r['remarks']): ?><small><strong>Remarks:</strong> <?php echo htmlspecialchars($r['remarks']); ?></small><?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info"><i class="fas fa-info-circle"></i> No final average requests found.</div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>