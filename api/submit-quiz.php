<?php
/**
 * Quiz submission endpoint (JSON only)
 *
 * If you see "Unexpected end of JSON input" in the browser, it usually means PHP
 * fatally errored and returned an empty response. This file is hardened to always
 * return valid JSON, even on fatal errors.
 */

// Do not display PHP errors as HTML in the response
ini_set('display_errors', 0);

use App\Application\QuizSubmissionService;

require_once '../config/config.php';
requireLogin();

// Always buffer output so we can return clean JSON
ob_start();

$__json_sent = false;

function send_json($payload, int $statusCode = 200) {
    global $__json_sent;
    $__json_sent = true;

    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

register_shutdown_function(function () {
    global $__json_sent;
    if ($__json_sent) {
        return;
    }

    $err = error_get_last();
    if (!$err) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) {
        return;
    }

    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error while submitting quiz.',
        'error' => $err['message'],
    ]);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$quizId = $data['quiz_id'] ?? 0;
$lessonId = $data['lesson_id'] ?? 0;
$answers = $data['answers'] ?? [];

if (!$quizId || !$lessonId) {
    send_json(['success' => false, 'message' => 'Quiz ID and Lesson ID are required'], 400);
}

$service = new QuizSubmissionService();
$result = $service->submitQuiz(getCurrentUserId(), $quizId, $lessonId, $answers);

$statusCode = $result['statusCode'] ?? 200;
if (isset($result['statusCode'])) {
    unset($result['statusCode']);
}

send_json($result, $statusCode);
