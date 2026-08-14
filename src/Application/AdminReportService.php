<?php

namespace App\Application;

use App\Core\Database;

final class AdminReportService
{
    public function getReportData(): array
    {
        $conn = Database::getConnection();

        $data = [];
        $data['totalStudents'] = $this->getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
        $data['totalTeachers'] = $this->getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'teacher'");
        $data['totalLessons'] = $this->getCount($conn, 'SELECT COUNT(*) AS total FROM lessons');
        $data['totalQuizzes'] = $this->getCount($conn, 'SELECT COUNT(*) AS total FROM quizzes');
        $data['completedLessons'] = $this->getCount($conn, "SELECT COUNT(*) AS total FROM student_progress WHERE status = 'completed'");
        $data['unlockedLessons'] = $this->getCount($conn, "SELECT COUNT(*) AS total FROM student_progress WHERE status IN ('unlocked', 'in_progress', 'completed')");
        $data['pendingRequests'] = $this->getCount($conn, "SELECT COUNT(*) AS total FROM exam_requests WHERE status = 'pending'");
        $data['approvedRequests'] = $this->getCount($conn, "SELECT COUNT(*) AS total FROM exam_requests WHERE status = 'approved'");
        $data['deniedRequests'] = $this->getCount($conn, "SELECT COUNT(*) AS total FROM exam_requests WHERE status = 'denied'");
        $data['topStudents'] = $this->getTopStudents($conn);
        $data['subjectStats'] = $this->getSubjectStats($conn);

        return $data;
    }

    private function getCount(
        \mysqli $conn,
        string $sql
    ): int {
        $result = $conn->query($sql);
        return $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;
    }

    private function getTopStudents(\mysqli $conn): array
    {
        $result = $conn->query(
            'SELECT u.name, u.username, AVG(fg.final_average) as avg_grade '
            . 'FROM users u '
            . 'INNER JOIN final_grades fg ON u.id = fg.student_id '
            . 'WHERE u.role = "student" '
            . 'GROUP BY u.id '
            . 'ORDER BY avg_grade DESC '
            . 'LIMIT 10'
        );

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    private function getSubjectStats(\mysqli $conn): array
    {
        $result = $conn->query(
            'SELECT s.name, s.code, '
            . 'COUNT(DISTINCT l.id) as total_lessons, '
            . 'COUNT(DISTINCT q.id) as total_quizzes, '
            . 'COUNT(DISTINCT sp.student_id) as students_enrolled '
            . 'FROM subjects s '
            . 'LEFT JOIN lessons l ON s.id = l.subject_id '
            . 'LEFT JOIN quizzes q ON l.id = q.lesson_id '
            . 'LEFT JOIN student_progress sp ON l.id = sp.lesson_id '
            . 'GROUP BY s.id '
            . 'ORDER BY s.name'
        );

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
