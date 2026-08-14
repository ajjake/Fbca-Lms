<?php

namespace App\Application;

use App\Core\Database;
use App\Core\Session;

final class StudentDashboardService
{
    public function getDashboardData(): array
    {
        $studentId = Session::getCurrentUserId();
        $studentLevel = Session::getCurrentUserLevel();
        $currentQuarter = $this->getCurrentQuarter();

        $conn = Database::getConnection();

        $student = $this->getStudentProfile($conn, $studentId);
        $subjectsCount = $this->getSubjectsCount($conn, $studentLevel);
        $completedLessons = $this->getLessonProgressCount($conn, $studentId, 'completed');
        $unlockedLessons = $this->getStudentProgressCount($conn, $studentId, ['unlocked', 'in_progress', 'completed']);
        $pendingRequests = $this->getExamRequestsCount($conn, $studentId);
        $avgGrade = $this->getFinalAverage($conn, $studentId);
        $recentLessons = $this->getRecentLessons($conn, $studentId, $studentLevel, $currentQuarter);
        $requestStatus = $this->getFinalAverageRequestStatus($conn, $studentId, $currentQuarter);

        $showFinalAverage = $this->shouldShowFinalAverage($conn, $studentId, $currentQuarter);

        return [
            'student' => $student,
            'currentQuarter' => $currentQuarter,
            'subjectsCount' => $subjectsCount,
            'completedLessons' => $completedLessons,
            'unlockedLessons' => $unlockedLessons,
            'pendingRequests' => $pendingRequests,
            'avgGrade' => $avgGrade,
            'recentLessons' => $recentLessons,
            'requestStatus' => $requestStatus,
            'showFinalAverage' => $showFinalAverage,
        ];
    }

    private function getCurrentQuarter(): int
    {
        $month = (int)date('n');

        if ($month >= 1 && $month <= 3) {
            return 1;
        }
        if ($month >= 4 && $month <= 6) {
            return 2;
        }
        if ($month >= 7 && $month <= 9) {
            return 3;
        }

        return 4;
    }

    private function getStudentProfile(\mysqli $conn, int $studentId): array
    {
        $columns = ['name', 'level'];

        foreach (['avatar', 'lrn', 'guardian_name', 'guardian_contact'] as $column) {
            $check = $conn->query("SHOW COLUMNS FROM users LIKE '{$column}'");
            if ($check && $check->num_rows > 0) {
                $columns[] = $column;
            }
        }

        $select = implode(', ', $columns);
        $stmt = $conn->prepare("SELECT {$select} FROM users WHERE id = ?");
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $student ?: [];
    }

    private function getSubjectsCount(\mysqli $conn, int $level): int
    {
        $stmt = $conn->prepare('SELECT COUNT(DISTINCT subject_id) as total FROM lessons WHERE level = ?');
        $stmt->bind_param('i', $level);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        return (int) $count;
    }

    private function getLessonProgressCount(\mysqli $conn, int $studentId, string $status): int
    {
        $stmt = $conn->prepare('SELECT COUNT(*) as total FROM student_progress WHERE student_id = ? AND status = ?');
        $stmt->bind_param('is', $studentId, $status);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        return (int) $count;
    }

    private function getStudentProgressCount(\mysqli $conn, int $studentId, array $statuses): int
    {
        $escapedStatuses = array_map(static function ($status) use ($conn) {
            return "'" . $conn->real_escape_string($status) . "'";
        }, $statuses);

        $statusList = implode(', ', $escapedStatuses);
        $sql = "SELECT COUNT(*) as total FROM student_progress WHERE student_id = ? AND status IN ({$statusList})";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        return (int) $count;
    }

    private function getExamRequestsCount(\mysqli $conn, int $studentId): int
    {
        $stmt = $conn->prepare('SELECT COUNT(*) as total FROM exam_requests WHERE student_id = ? AND status = "pending"');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        return (int) $count;
    }

    private function getFinalAverage(\mysqli $conn, int $studentId): float
    {
        $stmt = $conn->prepare('SELECT AVG(final_average) as avg_grade FROM final_grades WHERE student_id = ?');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $grade = $stmt->get_result()->fetch_assoc()['avg_grade'] ?? 0;
        $stmt->close();

        return (float) $grade;
    }

    private function getRecentLessons(\mysqli $conn, int $studentId, int $level, int $quarter): array
    {
        $stmt = $conn->prepare(
            'SELECT l.*, s.name as subject_name, sp.status
            FROM lessons l
            INNER JOIN subjects s ON l.subject_id = s.id
            LEFT JOIN student_progress sp ON l.id = sp.lesson_id AND sp.student_id = ?
            WHERE l.level = ? AND l.quarter = ?
            ORDER BY s.name, l.order_index
            LIMIT 6'
        );
        $stmt->bind_param('iii', $studentId, $level, $quarter);
        $stmt->execute();
        $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $lessons;
    }

    private function shouldShowFinalAverage(\mysqli $conn, int $studentId, int $quarter): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $check = $conn->query("SHOW TABLES LIKE 'final_average_requests'");

        if (!$check || $check->num_rows === 0) {
            return false;
        }

        $stmt = $conn->prepare('SELECT status FROM final_average_requests WHERE student_id = ? AND quarter = ? ORDER BY requested_at DESC LIMIT 1');
        $stmt->bind_param('ii', $studentId, $quarter);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return isset($result['status']) && $result['status'] === 'approved';
    }

    private function getFinalAverageRequestStatus(\mysqli $conn, int $studentId, int $quarter): ?string
    {
        $check = $conn->query("SHOW TABLES LIKE 'final_average_requests'");

        if (!$check || $check->num_rows === 0) {
            return null;
        }

        $stmt = $conn->prepare('SELECT status FROM final_average_requests WHERE student_id = ? AND quarter = ? ORDER BY requested_at DESC LIMIT 1');
        $stmt->bind_param('ii', $studentId, $quarter);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result['status'] ?? null;
    }

    private function isAdmin(): bool
    {
        return Session::getCurrentUserRole() === 'admin';
    }
}
