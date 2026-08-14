<?php
require_once __DIR__ . '/config/database.php';

$conn = getDBConnection();

$quizId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($quizId <= 0) {
    $res = $conn->query("SELECT id, title, lesson_id FROM quizzes ORDER BY id DESC LIMIT 10");
    echo "Usage: php debug-quiz-answers.php <quiz_id>\n\n";
    echo "Recent quizzes:\n";
    while ($row = $res->fetch_assoc()) {
        echo "{$row['id']} | {$row['title']} | lesson_id={$row['lesson_id']}\n";
    }
    closeDBConnection($conn);
    exit(0);
}

$stmt = $conn->prepare("
    SELECT q.id, q.title, q.passing_score, q.lesson_id, l.lesson_number, l.pace_number, s.code as subject_code
    FROM quizzes q
    INNER JOIN lessons l ON q.lesson_id = l.id
    INNER JOIN subjects s ON l.subject_id = s.id
    WHERE q.id = ?
");
$stmt->bind_param("i", $quizId);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    echo "Quiz not found: $quizId\n";
    closeDBConnection($conn);
    exit(1);
}

echo "Quiz: {$quiz['id']} | {$quiz['title']} | passing={$quiz['passing_score']} | lesson={$quiz['subject_code']} " . ($quiz['pace_number'] ?: $quiz['lesson_number']) . "\n\n";

$stmt = $conn->prepare("
    SELECT id, question_type, correct_answer, option_a, option_b, option_c, option_d
    FROM quiz_questions
    WHERE quiz_id = ?
    ORDER BY order_index, id
");
$stmt->bind_param("i", $quizId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($rows as $i => $q) {
    $n = $i + 1;
    $type = $q['question_type'];
    $ca = $q['correct_answer'];

    // Detect likely misconfiguration
    $warn = [];
    if ($type === 'multiple_choice' && in_array($ca, ['True', 'False', 'TRUE', 'FALSE'], true)) {
        $warn[] = "INVALID correct_answer for multiple_choice (should be A/B/C/D)";
    }
    if ($type === 'true_false' && in_array($ca, ['A', 'B', 'C', 'D'], true)) {
        $warn[] = "INVALID correct_answer for true_false (should be True/False)";
    }

    echo "Q{$n} type={$type} correct_answer={$ca}";
    if ($warn) echo "  <-- " . implode("; ", $warn);
    echo "\n";
    if ($type === 'multiple_choice') {
        echo "  A={$q['option_a']}\n";
        echo "  B={$q['option_b']}\n";
        echo "  C={$q['option_c']}\n";
        echo "  D={$q['option_d']}\n";
    }
}

closeDBConnection($conn);
?>
