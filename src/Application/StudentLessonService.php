<?php

namespace App\Application;

use App\Core\Database;

final class StudentLessonService
{
    public function getSubjects(): array
    {
        $conn = Database::getConnection();
        $result = $conn->query('SELECT * FROM subjects ORDER BY name');
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getFilteredLessons(int $studentId, int $level, int $quarter, ?int $subjectId = null): array
    {
        $conn = Database::getConnection();
        $sql = 
            'SELECT l.*, s.name as subject_name, s.code as subject_code, sp.status, '
            . '(SELECT COUNT(*) FROM lesson_scores ls WHERE ls.student_id = ? AND ls.lesson_id = l.id) as has_score '
            . 'FROM lessons l '
            . 'INNER JOIN subjects s ON l.subject_id = s.id '
            . 'LEFT JOIN student_progress sp ON l.id = sp.lesson_id AND sp.student_id = ? '
            . 'WHERE l.level = ? AND l.quarter = ? ';

        $params = [$studentId, $studentId, $level, $quarter];
        $types = 'iiii';

        if ($subjectId !== null) {
            $sql .= 'AND s.id = ? ';
            $params[] = $subjectId;
            $types .= 'i';
        }

        $sql .= 'ORDER BY s.name, l.order_index';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $lessons;
    }

    public function getLessonDetails(int $lessonId, int $studentId): ?array
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'SELECT l.*, s.name as subject_name, s.code as subject_code, sp.status '
            . 'FROM lessons l '
            . 'INNER JOIN subjects s ON l.subject_id = s.id '
            . 'LEFT JOIN student_progress sp ON l.id = sp.lesson_id AND sp.student_id = ? '
            . 'WHERE l.id = ?'
        );
        $stmt->bind_param('ii', $studentId, $lessonId);
        $stmt->execute();
        $lesson = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $lesson ?: null;
    }

    public function getQuizByLesson(int $lessonId): ?array
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT * FROM quizzes WHERE lesson_id = ?');
        $stmt->bind_param('i', $lessonId);
        $stmt->execute();
        $quiz = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $quiz ?: null;
    }

    public function getQuizDetails(int $quizId, int $lessonId): ?array
    {
        $conn = Database::getConnection();
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

    public function getQuizQuestions(int $quizId): array
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index, id');
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $questions ?: [];
    }

    public function getAttemptInfo(int $studentId, int $quizId): array
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'SELECT COUNT(*) as attempt_count,
                    MAX(percentage) as best_percentage,
                    MAX(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as has_passed
             FROM lesson_scores
             WHERE student_id = ? AND quiz_id = ?'
        );
        $stmt->bind_param('ii', $studentId, $quizId);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'total_attempts' => (int) ($info['attempt_count'] ?? 0),
            'best_percentage' => (float) ($info['best_percentage'] ?? 0),
            'has_passed' => (int) ($info['has_passed'] ?? 0),
        ];
    }

    public function getScoreDetails(int $studentId, int $lessonId, ?int $scoreId = null): ?array
    {
        $conn = Database::getConnection();

        if ($scoreId) {
            $stmt = $conn->prepare(
                'SELECT ls.*, q.title as quiz_title, l.title as lesson_title, l.lesson_number, l.pace_number, s.name as subject_name
                FROM lesson_scores ls
                INNER JOIN quizzes q ON ls.quiz_id = q.id
                INNER JOIN lessons l ON ls.lesson_id = l.id
                INNER JOIN subjects s ON l.subject_id = s.id
                WHERE ls.id = ? AND ls.student_id = ?'
            );
            $stmt->bind_param('ii', $scoreId, $studentId);
        } else {
            $stmt = $conn->prepare(
                'SELECT ls.*, q.title as quiz_title, l.title as lesson_title, l.lesson_number, l.pace_number, s.name as subject_name
                FROM lesson_scores ls
                INNER JOIN quizzes q ON ls.quiz_id = q.id
                INNER JOIN lessons l ON ls.lesson_id = l.id
                INNER JOIN subjects s ON l.subject_id = s.id
                WHERE ls.lesson_id = ? AND ls.student_id = ?
                ORDER BY ls.taken_at DESC
                LIMIT 1'
            );
            $stmt->bind_param('ii', $lessonId, $studentId);
        }

        $stmt->execute();
        $score = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $score ?: null;
    }

    public function getAllQuizAttempts(int $studentId, int $quizId): array
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'SELECT * FROM lesson_scores
            WHERE student_id = ? AND quiz_id = ?
            ORDER BY taken_at DESC'
        );
        $stmt->bind_param('ii', $studentId, $quizId);
        $stmt->execute();
        $attempts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $attempts ?: [];
    }

    public function getNextLessonUnlockInfo(int $lessonId, int $studentId): ?array
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'SELECT l.id, l.title, l.lesson_number, l.pace_number, sp.status
            FROM lessons l
            LEFT JOIN student_progress sp ON l.id = sp.lesson_id AND sp.student_id = ?
            WHERE l.subject_id = (SELECT subject_id FROM lessons WHERE id = ?)
              AND l.quarter = (SELECT quarter FROM lessons WHERE id = ?)
              AND l.level = (SELECT level FROM lessons WHERE id = ?)
              AND l.order_index = (SELECT order_index FROM lessons WHERE id = ?) + 1'
        );
        $stmt->bind_param('iiiii', $studentId, $lessonId, $lessonId, $lessonId, $lessonId);
        $stmt->execute();
        $nextLesson = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $nextLesson ?: null;
    }

    public function getExamRequest(int $studentId, int $lessonId): ?array
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT * FROM exam_requests WHERE student_id = ? AND lesson_id = ? AND request_type = "lesson_exam" ORDER BY requested_at DESC LIMIT 1');
        $stmt->bind_param('ii', $studentId, $lessonId);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $request ?: null;
    }

    public function getLatestQuizAttempt(int $studentId, int $lessonId): array
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'SELECT ls.*, '
            . '(SELECT COUNT(*) FROM lesson_scores WHERE student_id = ? AND quiz_id = ls.quiz_id) as total_attempts, '
            . '(SELECT MAX(percentage) FROM lesson_scores WHERE student_id = ? AND quiz_id = ls.quiz_id) as best_percentage, '
            . '(SELECT MAX(CASE WHEN passed = 1 THEN 1 ELSE 0 END) FROM lesson_scores WHERE student_id = ? AND quiz_id = ls.quiz_id) as has_passed '
            . 'FROM lesson_scores ls '
            . 'WHERE ls.student_id = ? AND ls.lesson_id = ? '
            . 'ORDER BY ls.taken_at DESC '
            . 'LIMIT 1'
        );
        $stmt->bind_param('iiiii', $studentId, $studentId, $studentId, $studentId, $lessonId);
        $stmt->execute();
        $attempt = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $attempt ?: [];
    }

    public function setLessonProgress(int $studentId, int $lessonId, string $status): void
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at, completed_at) '
            . 'VALUES (?, ?, ?, NOW(), CASE WHEN ? = "completed" THEN NOW() ELSE NULL END) '
            . 'ON DUPLICATE KEY UPDATE status = VALUES(status), unlocked_at = CASE WHEN VALUES(status) IN ("unlocked", "in_progress") THEN NOW() ELSE unlocked_at END, completed_at = CASE WHEN VALUES(status) = "completed" THEN NOW() ELSE completed_at END'
        );
        $stmt->bind_param('iiss', $studentId, $lessonId, $status, $status);
        $stmt->execute();
        $stmt->close();
    }

    public function unlockNextLessonIfApproved(int $lessonId, int $studentId): void
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'SELECT id FROM lessons '
            . 'WHERE subject_id = (SELECT subject_id FROM lessons WHERE id = ?) '
            . 'AND quarter = (SELECT quarter FROM lessons WHERE id = ?) '
            . 'AND level = (SELECT level FROM lessons WHERE id = ?) '
            . 'AND order_index = (SELECT order_index FROM lessons WHERE id = ?) + 1'
        );
        $stmt->bind_param('iiii', $lessonId, $lessonId, $lessonId, $lessonId);
        $stmt->execute();
        $nextLesson = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$nextLesson) {
            return;
        }

        $nextLessonId = $nextLesson['id'];
        $stmt = $conn->prepare('SELECT * FROM exam_requests WHERE student_id = ? AND lesson_id = ? AND status = "approved"');
        $stmt->bind_param('ii', $studentId, $nextLessonId);
        $stmt->execute();
        $approved = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($approved) {
            $stmt = $conn->prepare(
                'INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at) '
                . 'VALUES (?, ?, "unlocked", NOW()) '
                . 'ON DUPLICATE KEY UPDATE status = VALUES(status), unlocked_at = NOW()'
            );
            $stmt->bind_param('ii', $studentId, $nextLessonId);
            $stmt->execute();
            $stmt->close();
        }
    }
}
