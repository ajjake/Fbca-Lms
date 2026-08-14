<?php

namespace App\Application;

use App\Core\Database;

final class AdminDashboardService
{
    public function getDashboardData(): array
    {
        $conn = Database::getConnection();

        $totalStudents = $this->getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
        $totalTeachers = $this->getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'teacher'");
        $totalSubjects = $this->getCount($conn, 'SELECT COUNT(*) AS total FROM subjects');
        $totalLessons = $this->getCount($conn, 'SELECT COUNT(*) AS total FROM lessons');
        $totalQuizzes = $this->getCount($conn, 'SELECT COUNT(*) AS total FROM quizzes');
        $pendingRequests = $this->getCount($conn, "SELECT COUNT(*) AS total FROM exam_requests WHERE status = 'pending'");
        $recentUsers = $this->getRecentUsers($conn);

        return [
            'totalStudents' => $totalStudents,
            'totalTeachers' => $totalTeachers,
            'totalSubjects' => $totalSubjects,
            'totalLessons' => $totalLessons,
            'totalQuizzes' => $totalQuizzes,
            'pendingRequests' => $pendingRequests,
            'recentUsers' => $recentUsers,
        ];
    }

    private function getCount(\mysqli $conn, string $sql): int
    {
        $result = $conn->query($sql);
        return $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;
    }

    private function getRecentUsers(\mysqli $conn): array
    {
        $result = $conn->query('SELECT name, username, email, role, level, created_at FROM users ORDER BY created_at DESC LIMIT 5');
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
