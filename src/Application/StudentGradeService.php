<?php

namespace App\Application;

use App\Core\Database;

final class StudentGradeService
{
    public function getGradesForStudent(int $studentId): array
    {
        $conn = Database::getConnection();

        $subjects = [];
        $subjectResult = $conn->query('SELECT * FROM subjects ORDER BY name');
        if ($subjectResult) {
            $subjects = $subjectResult->fetch_all(MYSQLI_ASSOC);
        }

        $quarterGrades = [];
        $stmt = $conn->prepare('SELECT * FROM quarter_grades WHERE student_id = ?');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $grade) {
            $quarterGrades[$grade['subject_id']][$grade['quarter']] = $grade;
        }
        $stmt->close();

        $finalGrades = [];
        $stmt = $conn->prepare('SELECT * FROM final_grades WHERE student_id = ?');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $grade) {
            $finalGrades[$grade['subject_id']] = $grade;
        }
        $stmt->close();

        $overallAverage = 0;
        $subjectsWithFinal = 0;

        foreach ($subjects as &$subject) {
            $subjectId = $subject['id'];
            $subject['quarterGrades'] = $quarterGrades[$subjectId] ?? [];
            $subject['finalGrade'] = $finalGrades[$subjectId] ?? null;

            if (!empty($subject['finalGrade']['final_average'])) {
                $overallAverage += $subject['finalGrade']['final_average'];
                $subjectsWithFinal++;
            }
        }
        unset($subject);

        if ($subjectsWithFinal > 0) {
            $overallAverage = $overallAverage / $subjectsWithFinal;
        }

        return [
            'subjects' => $subjects,
            'overallAverage' => $overallAverage,
        ];
    }
}
