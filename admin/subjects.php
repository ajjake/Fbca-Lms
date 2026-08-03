<?php
require_once '../config/config.php';
requireRole(['admin']);

$conn = getDBConnection();

$message = '';
$error = '';

// Handle add/edit subject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $code = $_POST['code'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($name) || empty($code)) {
        $error = 'Please fill in all required fields.';
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE subjects SET name = ?, code = ?, description = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $code, $description, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO subjects (name, code, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $code, $description);
        }
        
        if ($stmt->execute()) {
            $message = $id > 0 ? 'Subject updated successfully.' : 'Subject added successfully.';
        } else {
            $error = 'Failed to save subject.';
        }
        $stmt->close();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'Subject deleted successfully.';
    }
    $stmt->close();
}

// Get subjects
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get subject to edit
$editSubject = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editSubject = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

closeDBConnection($conn);

$pageTitle = 'Manage Subjects';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">Manage Subjects</h1>
        <button onclick="showAddForm()" class="btn btn-primary">Add New Subject</button>
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
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $subject): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($subject['name']); ?></td>
                        <td><?php echo htmlspecialchars($subject['code']); ?></td>
                        <td><?php echo htmlspecialchars($subject['description'] ?? ''); ?></td>
                        <td>
                            <a href="?edit=<?php echo $subject['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="?delete=<?php echo $subject['id']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this subject?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Form -->
<div id="subjectFormModal" style="display: <?php echo $editSubject ? 'flex' : 'none'; ?>; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="max-width: 600px; margin: 2rem;">
        <div class="card-header">
            <h2 class="card-title"><?php echo $editSubject ? 'Edit Subject' : 'Add New Subject'; ?></h2>
            <button onclick="hideForm()" class="btn btn-secondary btn-sm">Close</button>
        </div>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $editSubject['id'] ?? 0; ?>">
            
            <div class="form-group">
                <label class="form-label" for="name">Subject Name *</label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?php echo htmlspecialchars($editSubject['name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="code">Subject Code *</label>
                <input type="text" class="form-control" id="code" name="code" 
                       value="<?php echo htmlspecialchars($editSubject['code'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($editSubject['description'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Subject
            </button>
        </form>
    </div>
</div>

<script>
function showAddForm() {
    window.location.href = 'subjects.php?add=1';
}

function hideForm() {
    window.location.href = 'subjects.php';
}
</script>

<?php include '../includes/footer.php'; ?>
