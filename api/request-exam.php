<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$lessonId = $data['lesson_id'] ?? 0;
$requestType = $data['request_type'] ?? 'lesson_exam';
$quarter = $data['quarter'] ?? null;

if (!$lessonId) {
    echo json_encode(['success' => false, 'message' => 'Lesson ID is required']);
    exit();
}

$conn = getDBConnection();
$studentId = getCurrentUserId();

// Check if request already exists
$stmt = $conn->prepare("SELECT * FROM exam_requests WHERE student_id = ? AND lesson_id = ? AND request_type = ?");
$stmt->bind_param("iis", $studentId, $lessonId, $requestType);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing && $existing['status'] === 'pending') {
    echo json_encode(['success' => false, 'message' => 'You already have a pending request for this exam']);
    closeDBConnection($conn);
    exit();
}

// Create new request
$stmt = $conn->prepare("
    INSERT INTO exam_requests (student_id, lesson_id, request_type, quarter, status)
    VALUES (?, ?, ?, ?, 'pending')
");
$stmt->bind_param("iisi", $studentId, $lessonId, $requestType, $quarter);
$result = $stmt->execute();
$stmt->close();

closeDBConnection($conn);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Exam request submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
}
?>
