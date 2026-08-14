<?php
use App\Application\ExamRequestService;

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
$quarter = isset($data['quarter']) ? (int) $data['quarter'] : null;

if (!$lessonId) {
    echo json_encode(['success' => false, 'message' => 'Lesson ID is required']);
    exit();
}

$service = new ExamRequestService();
$result = $service->submitRequest(getCurrentUserId(), $lessonId, $requestType, $quarter);

echo json_encode($result);
?>
