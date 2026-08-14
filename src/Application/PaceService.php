<?php

namespace App\Application;

use App\Core\Database;

final class PaceService
{
    public function canAccessTest(int $studentId, int $testLessonId): bool
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'SELECT subject_id, quarter, level, pace_type FROM lessons WHERE id = ?'
        );
        $stmt->bind_param('i', $testLessonId);
        $stmt->execute();
        $test = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$test) {
            return false;
        }

        $requiredPaces = $test['pace_type'] === 'monthly_test' ? 2 : 3;

        $stmt = $conn->prepare(
            'SELECT COUNT(*) as completed_count '
            . 'FROM student_progress sp '
            . 'INNER JOIN lessons l ON sp.lesson_id = l.id '
            . 'WHERE sp.student_id = ? '
            . 'AND l.subject_id = ? '
            . 'AND l.quarter = ? '
            . 'AND l.level = ? '
            . 'AND l.pace_type = "lesson" '
            . 'AND sp.status = "completed"'
        );
        $stmt->bind_param('iiii', $studentId, $test['subject_id'], $test['quarter'], $test['level']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return ($result['completed_count'] ?? 0) >= $requiredPaces;
    }

    public function unlockNextPace(int $studentId, int $completedLessonId): void
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT subject_id, quarter, level, order_index, pace_type FROM lessons WHERE id = ?');
        $stmt->bind_param('i', $completedLessonId);
        $stmt->execute();
        $lesson = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$lesson) {
            return;
        }

        $subjectId = $lesson['subject_id'];
        $quarter = $lesson['quarter'];
        $level = $lesson['level'];
        $orderIndex = $lesson['order_index'];
        $paceType = $lesson['pace_type'] ?? 'lesson';

        if ($paceType === 'lesson') {
            $this->unlockNextLesson($studentId, $subjectId, $quarter, $level, $orderIndex + 1);

            if ($orderIndex + 1 >= 2) {
                $this->unlockPaceType($studentId, $subjectId, $quarter, $level, 'monthly_test');
            }

            if ($orderIndex + 1 >= 3) {
                $this->unlockPaceType($studentId, $subjectId, $quarter, $level, 'quarter_test');
            }
        }

        if ($paceType === 'quarter_test') {
            $stmt = $conn->prepare(
                'SELECT passed FROM lesson_scores '
                . 'WHERE student_id = ? AND lesson_id = ? '
                . 'ORDER BY taken_at DESC LIMIT 1'
            );
            $stmt->bind_param('ii', $studentId, $completedLessonId);
            $stmt->execute();
            $scoreResult = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($scoreResult && $scoreResult['passed']) {
                $this->unlockNextLesson($studentId, $subjectId, $quarter + 1, $level, 0, 'lesson');
            }
        }
    }

    public function unlockFirstPacesForNewStudent(int $studentId, int $level): void
    {
        $conn = Database::getConnection();
        $subjects = $conn->query('SELECT id, code FROM subjects')->fetch_all(MYSQLI_ASSOC);

        foreach ($subjects as $subject) {
            for ($quarter = 1; $quarter <= 4; $quarter++) {
                $basePace = 1013 + ($level - 1) * 12 + ($quarter - 1) * 3;
                $paceNumberWithCode = $subject['code'] . ' ' . $basePace;
                $paceNumberOnly = (string) $basePace;

                $stmt = $conn->prepare(
                    'SELECT id FROM lessons '
                    . 'WHERE subject_id = ? AND quarter = ? AND level = ? '
                    . 'AND (pace_number = ? OR pace_number = ? OR lesson_number = ? OR lesson_number = ?) '
                    . 'AND pace_type = "lesson" '
                    . 'ORDER BY order_index ASC LIMIT 1'
                );
                $stmt->bind_param('iiissss', $subject['id'], $quarter, $level, $paceNumberWithCode, $paceNumberOnly, $paceNumberWithCode, $paceNumberOnly);
                $stmt->execute();
                $firstPace = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($firstPace) {
                    $this->unlockStudentProgress($studentId, $firstPace['id']);
                }
            }
        }
    }

    private function unlockNextLesson(int $studentId, int $subjectId, int $quarter, int $level, int $orderIndex, string $requiredPaceType = 'lesson'): void
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'SELECT id FROM lessons '
            . 'WHERE subject_id = ? AND quarter = ? AND level = ? AND order_index = ? AND pace_type = ? '
            . 'LIMIT 1'
        );
        $stmt->bind_param('iiiis', $subjectId, $quarter, $level, $orderIndex, $requiredPaceType);
        $stmt->execute();
        $nextPace = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($nextPace) {
            $this->unlockStudentProgress($studentId, $nextPace['id']);
        }
    }

    private function unlockPaceType(int $studentId, int $subjectId, int $quarter, int $level, string $paceType): void
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'SELECT id FROM lessons '
            . 'WHERE subject_id = ? AND quarter = ? AND level = ? AND pace_type = ? '
            . 'LIMIT 1'
        );
        $stmt->bind_param('iiis', $subjectId, $quarter, $level, $paceType);
        $stmt->execute();
        $lesson = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($lesson) {
            $this->unlockStudentProgress($studentId, $lesson['id']);
        }
    }

    private function unlockStudentProgress(int $studentId, int $lessonId): void
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            'INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at) '
            . 'VALUES (?, ?, "unlocked", NOW()) '
            . 'ON DUPLICATE KEY UPDATE status = VALUES(status), unlocked_at = NOW()'
        );
        $stmt->bind_param('ii', $studentId, $lessonId);
        $stmt->execute();
        $stmt->close();
    }
}
