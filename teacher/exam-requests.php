<?php
use App\Application\TeacherExamRequestService;

require_once '../config/config.php';
requireRole(['teacher']);

$service = new TeacherExamRequestService();
$teacherId = getCurrentUserId();
$filter = $_GET['filter'] ?? 'all';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $requestId = (int) $_GET['id'];
    $remarks = $_POST['remarks'] ?? '';

    if ($service->processRequest($teacherId, $requestId, $action, $remarks)) {
        header('Location: exam-requests.php?message=' . urlencode('Request ' . $action . 'd successfully'));
        exit();
    }
}

$requests = $service->getRequests($teacherId, $filter);

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
        <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
        <a href="?filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">Pending</a>
        <a href="?filter=approved" class="btn <?php echo $filter === 'approved' ? 'btn-primary' : 'btn-secondary'; ?>">Approved</a>
        <a href="?filter=denied" class="btn <?php echo $filter === 'denied' ? 'btn-primary' : 'btn-secondary'; ?>">Denied</a>
    </div>
    
    <?php if (empty($requests)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No exam requests found.
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Lesson</th>
                        <th>Subject</th>
                        <th>Request Type</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($request['student_name']); ?><br>
                                <small>Level <?php echo $request['student_level']; ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($request['lesson_number']); ?><br>
                                <small><?php echo htmlspecialchars($request['lesson_title']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($request['subject_name']); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($request['requested_at'])); ?></td>
                            <td>
                                <span class="badge <?php 
                                    echo $request['status'] === 'approved' ? 'badge-success' : 
                                        ($request['status'] === 'denied' ? 'badge-danger' : 'badge-warning'); 
                                ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($request['status'] === 'pending'): ?>
                                    <form method="POST" action="?action=approve&id=<?php echo $request['id']; ?>" style="display: inline;">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="showDenyForm(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-times"></i> Deny
                                    </button>
                                <?php else: ?>
                                    <?php if ($request['remarks']): ?>
                                        <small><strong>Remarks:</strong> <?php echo htmlspecialchars($request['remarks']); ?></small><br>
                                    <?php endif; ?>
                                    <?php if ($request['reviewer_name']): ?>
                                        <small>Reviewed by: <?php echo htmlspecialchars($request['reviewer_name']); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Deny Form Modal -->
<div id="denyModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="max-width: 500px; margin: 2rem;">
        <div class="card-header">
            <h2 class="card-title">Deny Exam Request</h2>
        </div>
        <form method="POST" id="denyForm">
            <div class="form-group">
                <label class="form-label">Remarks (Optional)</label>
                <textarea class="form-control" name="remarks" rows="3"></textarea>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-danger">Confirm Deny</button>
                <button type="button" class="btn btn-secondary" onclick="hideDenyForm()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showDenyForm(requestId) {
    document.getElementById('denyForm').action = '?action=deny&id=' + requestId;
    document.getElementById('denyModal').style.display = 'flex';
}

function hideDenyForm() {
    document.getElementById('denyModal').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>
