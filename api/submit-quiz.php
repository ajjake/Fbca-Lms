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
        // Keep message for debugging; remove if you don't want to expose it.
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

try {
    $conn = getDBConnection();
    $studentId = getCurrentUserId();
} catch (Exception $e) {
    send_json(['success' => false, 'message' => 'Database connection error'], 500);
}

// Get quiz details
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt || send_json(['success' => false, 'message' => 'Database error: failed to prepare quiz query.'], 500);
$stmt->bind_param("i", $quizId);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    closeDBConnection($conn);
    send_json(['success' => false, 'message' => 'Quiz not found'], 404);
}

// Get all questions
$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ?");
$stmt || send_json(['success' => false, 'message' => 'Database error: failed to prepare questions query.'], 500);
$stmt->bind_param("i", $quizId);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate score
$totalPoints = 0;
$earnedPoints = 0;

foreach ($questions as $question) {
    $totalPoints += $question['points'];
    $questionKey = 'question_' . $question['id'];
    $studentAnswer = $answers[$questionKey] ?? '';
    
    if (strtoupper(trim($studentAnswer)) === strtoupper(trim($question['correct_answer']))) {
        $earnedPoints += $question['points'];
    }
}

$percentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
$passed = $percentage >= $quiz['passing_score'];

// Check existing attempts
$stmt = $conn->prepare("SELECT COUNT(*) as attempt_count FROM lesson_scores WHERE student_id = ? AND quiz_id = ?");
$stmt || send_json(['success' => false, 'message' => 'Database error: failed to prepare attempts query.'], 500);
$stmt->bind_param("ii", $studentId, $quizId);
$stmt->execute();
$attemptResult = $stmt->get_result()->fetch_assoc();
$attemptCount = $attemptResult['attempt_count'];
$stmt->close();

// Calculate attempt number (next attempt)
$attemptNumber = $attemptCount + 1;

// Check if student has exceeded max attempts (3 total = 1 initial + 2 retakes)
$maxAttempts = 3;
if ($attemptNumber > $maxAttempts) {
    closeDBConnection($conn);
    send_json([
        'success' => false,
        'message' => 'You have exceeded the maximum number of attempts (3). Please contact your teacher for assistance.'
    ], 403);
}

// Get best score so far
$stmt = $conn->prepare("SELECT MAX(percentage) as best_percentage FROM lesson_scores WHERE student_id = ? AND quiz_id = ?");
$stmt || send_json(['success' => false, 'message' => 'Database error: failed to prepare best-score query.'], 500);
$stmt->bind_param("ii", $studentId, $quizId);
$stmt->execute();
$bestResult = $stmt->get_result()->fetch_assoc();
$bestPercentage = $bestResult['best_percentage'] ?? 0;
$stmt->close();

// Determine if this is the best score
$isBestScore = ($percentage > $bestPercentage) || ($attemptNumber == 1);
$isBestScoreInt = $isBestScore ? 1 : 0;

// If this is better than previous best, mark previous as not best
if ($isBestScore && $attemptNumber > 1) {
    $stmt = $conn->prepare("UPDATE lesson_scores SET is_best_score = FALSE WHERE student_id = ? AND quiz_id = ?");
    $stmt || send_json(['success' => false, 'message' => 'Database error: failed to prepare best-score update.'], 500);
    $stmt->bind_param("ii", $studentId, $quizId);
    $stmt->execute();
    $stmt->close();
}

// Save score
$stmt = $conn->prepare("
    INSERT INTO lesson_scores (student_id, quiz_id, lesson_id, score, total_points, percentage, passed, time_taken, attempt_number, is_best_score)
    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
");
if (!$stmt) {
    closeDBConnection($conn);
    send_json(['success' => false, 'message' => 'Database error: failed to prepare score insert.', 'error' => $conn->error], 500);
}
$stmt->bind_param("iiidddiii", $studentId, $quizId, $lessonId, $earnedPoints, $totalPoints, $percentage, $passed, $attemptNumber, $isBestScoreInt);
if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    closeDBConnection($conn);
    send_json(['success' => false, 'message' => 'Database error: failed to save score.', 'error' => $err], 500);
}
$scoreId = $conn->insert_id;
$stmt->close();

// Update student progress
if ($passed) {
    // Mark lesson as completed
    $stmt = $conn->prepare("
        UPDATE student_progress 
        SET status = 'completed', completed_at = NOW()
        WHERE student_id = ? AND lesson_id = ?
    ");
    $stmt || send_json(['success' => false, 'message' => 'Database error: failed to prepare progress update.'], 500);
    $stmt->bind_param("ii", $studentId, $lessonId);
    $stmt->execute();
    $stmt->close();
    
    // Unlock next PACE/test based on PACE system rules
    require_once '../includes/pace-unlock.php';
    unlockNextPace($conn, $studentId, $lessonId);
    
    // Unlock next lesson
    $stmt = $conn->prepare("
        SELECT id FROM lessons 
        WHERE subject_id = (SELECT subject_id FROM lessons WHERE id = ?)
        AND quarter = (SELECT quarter FROM lessons WHERE id = ?)
        AND level = (SELECT level FROM lessons WHERE id = ?)
        AND order_index = (SELECT order_index FROM lessons WHERE id = ?) + 1
    ");
    $currentLesson = $lessonId;
    $stmt->bind_param("iiii", $currentLesson, $currentLesson, $currentLesson, $currentLesson);
    $stmt->execute();
    $nextLesson = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($nextLesson) {
        // Check if exam request is approved for next lesson
        $stmt = $conn->prepare("
            SELECT * FROM exam_requests 
            WHERE student_id = ? AND lesson_id = ? AND status = 'approved'
        ");
        $stmt->bind_param("ii", $studentId, $nextLesson['id']);
        $stmt->execute();
        $approved = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($approved) {
            $stmt = $conn->prepare("
                INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                VALUES (?, ?, 'unlocked', NOW())
                ON DUPLICATE KEY UPDATE status = 'unlocked', unlocked_at = NOW()
            ");
            $stmt->bind_param("ii", $studentId, $nextLesson['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Update quarter grades (always compute, not just when passed)
    try {
        require_once '../includes/grading.php';
        updateQuarterGrades($conn, $studentId, $lessonId);
    } catch (Exception $e) {
        // Silently fail grading update - don't break quiz submission
    }
} else {
    // Even if not passed, update grades to reflect current progress
    try {
        require_once '../includes/grading.php';
        updateQuarterGrades($conn, $studentId, $lessonId);
    } catch (Exception $e) {
        // Silently fail grading update - don't break quiz submission
    }
}

closeDBConnection($conn);

send_json([
    'success' => true,
    'score_id' => $scoreId,
    'score' => $earnedPoints,
    'total' => $totalPoints,
    'percentage' => $percentage,
    'passed' => $passed,
    'attempt_number' => $attemptNumber,
    'remaining_attempts' => max(0, $maxAttempts - $attemptNumber),
    'is_best_score' => $isBestScore,
    'best_percentage' => max($percentage, $bestPercentage),
]);
