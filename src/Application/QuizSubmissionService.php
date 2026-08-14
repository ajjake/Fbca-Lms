<?php

namespace App\Application;

use App\Core\Database;

final class QuizSubmissionService
{
    public function submitQuiz(int $studentId, int $quizId, int $lessonId, array $answers): array
    {
        $conn = Database::getConnection();
        $quiz = $this->findQuiz($conn, $quizId, $lessonId);

        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found', 'statusCode' => 404];
        }

        $questions = $this->getQuizQuestions($conn, $quizId);
        if (empty($questions)) {
            return ['success' => false, 'message' => 'No quiz questions found', 'statusCode' => 404];
        }

        $scoreData = $this->calculateScore($questions, $answers);
        $attemptInfo = $this->getAttemptInfo($conn, $studentId, $quizId);
        $attemptNumber = $attemptInfo['attemptCount'] + 1;
        $maxAttempts = 3;

        if ($attemptNumber > $maxAttempts) {
            return [
                'success' => false,
                'message' => 'You have exceeded the maximum number of attempts (3). Please contact your teacher for assistance.',
                'statusCode' => 403,
            ];
        }

        $isBestScore = $this->isBestScore($scoreData['percentage'], $attemptInfo['bestPercentage'], $attemptNumber);
        if ($isBestScore && $attemptNumber > 1) {
            $this->setPreviousBestScoreFalse($conn, $studentId, $quizId);
        }

        $scoreId = $this->saveScore(
            $conn,
            $studentId,
            $quizId,
            $lessonId,
            $scoreData,
            $attemptNumber,
            $isBestScore
        );

        if ($scoreData['passed']) {
            $this->markLessonCompleted($conn, $studentId, $lessonId);
            $this->unlockNextProgress($studentId, $lessonId);
        }

        try {
            (new GradingService())->updateQuarterGrades($studentId, $lessonId);
        } catch (\Throwable $e) {
            // Grades update failures should not break quiz submission.
        }

        $remainingAttempts = max(0, $maxAttempts - $attemptNumber);

        return [
            'success' => true,
            'score_id' => $scoreId,
            'score' => $scoreData['score'],
            'total' => $scoreData['totalPoints'],
            'percentage' => $scoreData['percentage'],
            'passed' => $scoreData['passed'],
            'attempt_number' => $attemptNumber,
            'remaining_attempts' => $remainingAttempts,
            'is_best_score' => $isBestScore,
            'best_percentage' => max($scoreInfo['bestPercentage'] ?? 0, $scoreData['percentage']),
        ];
    }

    private function findQuiz(\mysqli $conn, int $quizId, int $lessonId): ?array
    {
        $stmt = $conn->prepare(
            'SELECT q.*, l.title as lesson_title, l.lesson_number, l.pace_number, s.name as subject_name
            FROM quizzes q
            INNER JOIN lessons l ON q.lesson_id = l.id
            INNER JOIN subjects s ON l.subject_id = s.id
            WHERE q.id = ? AND l.id = ?'
        );
        $stmt->bind_param('ii', $quizId, $lessonId);
        $stmt->execute();
        $quiz = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $quiz ?: null;
    }

    private function getQuizQuestions(\mysqli $conn, int $quizId): array
    {
        $stmt = $conn->prepare('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index, id');
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $questions ?: [];
    }

    private function calculateScore(array $questions, array $answers): array
    {
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
        $passed = $percentage >= $questions[0]['passing_score'];

        return [
            'totalPoints' => $totalPoints,
            'score' => $earnedPoints,
            'percentage' => $percentage,
            'passed' => $passed,
        ];
    }

    private function getAttemptInfo(\mysqli $conn, int $studentId, int $quizId): array
    {
        $stmt = $conn->prepare(
            'SELECT COUNT(*) as attempt_count, MAX(percentage) as best_percentage
            FROM lesson_scores WHERE student_id = ? AND quiz_id = ?'
        );
        $stmt->bind_param('ii', $studentId, $quizId);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'attemptCount' => (int) ($info['attempt_count'] ?? 0),
            'bestPercentage' => (float) ($info['best_percentage'] ?? 0),
        ];
    }

    private function isBestScore(float $percentage, float $bestPercentage, int $attemptNumber): bool
    {
        return $percentage > $bestPercentage || $attemptNumber === 1;
    }

    private function setPreviousBestScoreFalse(\mysqli $conn, int $studentId, int $quizId): void
    {
        $stmt = $conn->prepare('UPDATE lesson_scores SET is_best_score = FALSE WHERE student_id = ? AND quiz_id = ?');
        $stmt->bind_param('ii', $studentId, $quizId);
        $stmt->execute();
        $stmt->close();
    }

    private function saveScore(\mysqli $conn, int $studentId, int $quizId, int $lessonId, array $scoreData, int $attemptNumber, bool $isBestScore): int
    {
        $isBestScoreInt = $isBestScore ? 1 : 0;
        $passedInt = $scoreData['passed'] ? 1 : 0;

        $stmt = $conn->prepare(
            'INSERT INTO lesson_scores (student_id, quiz_id, lesson_id, score, total_points, percentage, passed, time_taken, attempt_number, is_best_score)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
        );
        $stmt->bind_param(
            'iiidddiii',
            $studentId,
            $quizId,
            $lessonId,
            $scoreData['score'],
            $scoreData['totalPoints'],
            $scoreData['percentage'],
            $passedInt,
            $attemptNumber,
            $isBestScoreInt
        );
        $stmt->execute();
        $scoreId = $conn->insert_id;
        $stmt->close();

        return $scoreId;
    }

    private function markLessonCompleted(\mysqli $conn, int $studentId, int $lessonId): void
    {
        $stmt = $conn->prepare(
            'UPDATE student_progress
            SET status = "completed", completed_at = NOW()
            WHERE student_id = ? AND lesson_id = ?'
        );
        $stmt->bind_param('ii', $studentId, $lessonId);
        $stmt->execute();
        $stmt->close();
    }

    private function unlockNextProgress(int $studentId, int $lessonId): void
    {
        $paceService = new PaceService();
        $studentLessonService = new StudentLessonService();

        $paceService->unlockNextPace($studentId, $lessonId);
        $studentLessonService->unlockNextLessonIfApproved($lessonId, $studentId);
    }
}
