<?php

namespace App\Application;

use App\Core\Database;

final class TeacherDashboardService
{
    public function getDashboardData(int $teacherId): array
    {
        $conn = Database::getConnection();

        $teacher = $this->getTeacher($conn, $teacherId);
        $assignedSubjects = $this->getAssignedSubjects($conn, $teacherId);
        $assignedLevels = $this->getAssignedLevels($conn, $teacherId);
        $totalStudents = $this->getStudentCount($conn, $assignedLevels);
        $pendingRequests = $this->getPendingExamRequests($conn, $teacherId);
        $totalLessons = $this->getTotalLessons($conn, $teacherId);
        $recentRequests = $this->getRecentRequests($conn, $teacherId);

        return [
            'teacher' => $teacher,
            'assignedSubjects' => $assignedSubjects,
            'totalStudents' => $totalStudents,
            'pendingRequests' => $pendingRequests,
            'totalLessons' => $totalLessons,
            'recentRequests' => $recentRequests,
        ];
    }

    private function getTeacher(\mysqli $conn, int $teacherId): array
    {
        $stmt = $conn->prepare('SELECT name FROM users WHERE id = ?');
        $stmt->bind_param('i', $teacherId);
        $stmt->execute();
        $teacher = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $teacher ?: [];
    }

    private function getAssignedSubjects(\mysqli $conn, int $teacherId): array
    {
        $stmt = $conn->prepare('SELECT s.* FROM subjects s INNER JOIN teacher_subjects ts ON s.id = ts.subject_id WHERE ts.teacher_id = ?');
        $stmt->bind_param('i', $teacherId);
        $stmt->execute();
        $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $subjects;
    }

    private function getAssignedLevels(\mysqli $conn, int $teacherId): array
    {
        $stmt = $conn->prepare('SELECT level FROM teacher_levels WHERE teacher_id = ?');
        $stmt->bind_param('i', $teacherId);
        $stmt->execute();
        $levels = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'level');
        $stmt->close();

        return $levels;
    }

    private function getStudentCount(\mysqli $conn, array $levels): int
    {
        if (empty($levels)) {
            return $this->getCount($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'student'");
        }

        $levelList = implode(',', array_map('intval', $levels));
        $sql = "SELECT COUNT(*) as total FROM users WHERE role = 'student' AND level IN ({$levelList})";
        return $this->getCount($conn, $sql);
    }

    private function getPendingExamRequests(\mysqli $conn, int $teacherId): int
    {
        $stmt = $conn->prepare(
            'SELECT COUNT(*) as total FROM exam_requests er
            INNER JOIN lessons l ON er.lesson_id = l.id
            INNER JOIN teacher_subjects ts ON l.subject_id = ts.subject_id
            WHERE ts.teacher_id = ? AND er.status = "pending"'
        );
        $stmt->bind_param('i', $teacherId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        return (int) $count;
    }

    private function getTotalLessons(\mysqli $conn, int $teacherId): int
    {
        $stmt = $conn->prepare(
            'SELECT COUNT(*) as total FROM lessons l
            INNER JOIN teacher_subjects ts ON l.subject_id = ts.subject_id
            WHERE ts.teacher_id = ?'
        );
        $stmt->bind_param('i', $teacherId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        return (int) $count;
    }

    private function getRecentRequests(\mysqli $conn, int $teacherId): array
    {
        $stmt = $conn->prepare(
            'SELECT er.id, u.name AS student_name, l.title AS lesson_title, l.lesson_number, s.name AS subject_name, er.request_type, er.requested_at
            FROM exam_requests er
            INNER JOIN users u ON er.student_id = u.id
            INNER JOIN lessons l ON er.lesson_id = l.id
            INNER JOIN subjects s ON l.subject_id = s.id
            INNER JOIN teacher_subjects ts ON l.subject_id = ts.subject_id
            WHERE ts.teacher_id = ? AND er.status = "pending"
            ORDER BY er.requested_at DESC
            LIMIT 5'
        );
        $stmt->bind_param('i', $teacherId);
        $stmt->execute();
        $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $requests;
    }

    private function getCount(\mysqli $conn, string $sql): int
    {
        $result = $conn->query($sql);
        return $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;
    }
}
