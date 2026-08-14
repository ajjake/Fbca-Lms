<?php

namespace App\Application;

use App\Core\Database;

final class GradingService
{
    public function updateQuarterGrades(int $studentId, int $lessonId): void
    {
        $conn = Database::getConnection();

        $stmt = $conn->prepare('SELECT subject_id, quarter, level FROM lessons WHERE id = ?');
        $stmt->bind_param('i', $lessonId);
        $stmt->execute();
        $lesson = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$lesson) {
            return;
        }

        $subjectId = $lesson['subject_id'];
        $quarter = $lesson['quarter'];
        $level = $lesson['level'];

        $stmt = $conn->prepare(
            'SELECT MAX(ls.percentage) as best_percentage, ls.lesson_id '
            . 'FROM lesson_scores ls '
            . 'INNER JOIN lessons l ON ls.lesson_id = l.id '
            . 'WHERE ls.student_id = ? AND l.subject_id = ? AND l.quarter = ? AND l.level = ? '
            . 'GROUP BY ls.lesson_id'
        );
        $stmt->bind_param('iiii', $studentId, $subjectId, $quarter, $level);
        $stmt->execute();
        $scores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $lessonAverage = 0;
        if (count($scores) > 0) {
            $total = 0;
            foreach ($scores as $score) {
                $total += $score['best_percentage'];
            }
            $lessonAverage = round($total / count($scores), 2);
        }

        $stmt = $conn->prepare(
            'SELECT percentage FROM quarter_exam_scores WHERE student_id = ? AND subject_id = ? AND quarter = ?'
        );
        $stmt->bind_param('iii', $studentId, $subjectId, $quarter);
        $stmt->execute();
        $examResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $quarterExamScore = $examResult ? round($examResult['percentage'], 2) : 0;
        $finalGrade = $quarterExamScore > 0 ? round(($lessonAverage + $quarterExamScore) / 2, 2) : $lessonAverage;

        $stmt = $conn->prepare(
            'INSERT INTO quarter_grades (student_id, subject_id, quarter, lesson_average, quarter_exam_score, final_grade) '
            . 'VALUES (?, ?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE lesson_average = VALUES(lesson_average), quarter_exam_score = VALUES(quarter_exam_score), final_grade = VALUES(final_grade)'
        );
        $stmt->bind_param('iiiddd', $studentId, $subjectId, $quarter, $lessonAverage, $quarterExamScore, $finalGrade);
        $stmt->execute();
        $stmt->close();

        $this->updateFinalGrades($studentId, $subjectId);
    }

    public function updateFinalGrades(int $studentId, int $subjectId): void
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT quarter, final_grade FROM quarter_grades WHERE student_id = ? AND subject_id = ?');
        $stmt->bind_param('ii', $studentId, $subjectId);
        $stmt->execute();
        $quarters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $grades = ['q1' => 0, 'q2' => 0, 'q3' => 0, 'q4' => 0];
        foreach ($quarters as $q) {
            $grades['q' . $q['quarter']] = $q['final_grade'];
        }

        $values = array_values($grades);
        $quartersWithGrades = count(array_filter($values, static fn($grade) => $grade > 0));
        $finalAverage = $quartersWithGrades > 0 ? round(array_sum($values) / $quartersWithGrades, 2) : 0;

        $stmt = $conn->prepare(
            'INSERT INTO final_grades (student_id, subject_id, q1_grade, q2_grade, q3_grade, q4_grade, final_average) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE q1_grade = VALUES(q1_grade), q2_grade = VALUES(q2_grade), q3_grade = VALUES(q3_grade), q4_grade = VALUES(q4_grade), final_average = VALUES(final_average)'
        );
        $stmt->bind_param('iiddddd', $studentId, $subjectId, $grades['q1'], $grades['q2'], $grades['q3'], $grades['q4'], $finalAverage);
        $stmt->execute();
        $stmt->close();
    }

    public function recalculateAllGrades(): array
    {
        $conn = Database::getConnection();
        $students = $conn->query('SELECT id FROM users WHERE role = "student"')->fetch_all(MYSQLI_ASSOC);
        $subjects = $conn->query('SELECT id FROM subjects')->fetch_all(MYSQLI_ASSOC);

        $processed = 0;
        $errors = 0;

        foreach ($students as $student) {
            foreach ($subjects as $subject) {
                $stmt = $conn->prepare('SELECT id FROM lessons WHERE subject_id = ?');
                $stmt->bind_param('i', $subject['id']);
                $stmt->execute();
                $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                foreach ($lessons as $lesson) {
                    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM lesson_scores WHERE student_id = ? AND lesson_id = ?');
                    $stmt->bind_param('ii', $student['id'], $lesson['id']);
                    $stmt->execute();
                    $hasScores = (int) ($stmt->get_result()->fetch_assoc()['count'] ?? 0) > 0;
                    $stmt->close();

                    if ($hasScores) {
                        try {
                            $this->updateQuarterGrades($student['id'], $lesson['id']);
                            $processed++;
                        } catch (\Exception $e) {
                            $errors++;
                        }
                    }
                }
            }
        }

        return ['processed' => $processed, 'errors' => $errors];
    }
}
