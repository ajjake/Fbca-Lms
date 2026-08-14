<?php
use App\Application\FinalAverageRequestService;

require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$quarter = isset($data['quarter']) ? (int) $data['quarter'] : getCurrentQuarter();
$studentId = getCurrentUserId();

$service = new FinalAverageRequestService();
$result = $service->submitRequest($studentId, $quarter);

echo json_encode($result);
?>