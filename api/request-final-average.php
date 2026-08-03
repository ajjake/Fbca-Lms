<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$quarter = isset($data['quarter']) ? (int)$data['quarter'] : getCurrentQuarter();
$studentId = getCurrentUserId();

$conn = getDBConnection();

// Ensure table exists (simple migration)
$conn->query("CREATE TABLE IF NOT EXISTS final_average_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    quarter INT NOT NULL,
    status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    remarks TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_quarter (student_id, quarter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Check for an existing pending/approved request for same quarter
$stmt = $conn->prepare("SELECT * FROM final_average_requests WHERE student_id = ? AND quarter = ? ORDER BY requested_at DESC LIMIT 1");
$stmt->bind_param("ii", $studentId, $quarter);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing && $existing['status'] === 'pending') {
    echo json_encode(['success' => false, 'message' => 'You already have a pending request for this quarter']);
    closeDBConnection($conn);
    exit();
}

if ($existing && $existing['status'] === 'approved') {
    echo json_encode(['success' => false, 'message' => 'Your final average for this quarter has already been approved']);
    closeDBConnection($conn);
    exit();
}

// Create request
$stmt = $conn->prepare("INSERT INTO final_average_requests (student_id, quarter, status) VALUES (?, ?, 'pending')");
$stmt->bind_param("ii", $studentId, $quarter);
$result = $stmt->execute();
$stmt->close();

closeDBConnection($conn);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Final average request submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
}

?>