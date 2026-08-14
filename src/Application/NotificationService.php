<?php

namespace App\Application;

use App\Core\Database;

final class NotificationService
{
    public function getPendingExamRequestsCount(): int
    {
        $conn = Database::getConnection();
        $result = $conn->query("SELECT COUNT(*) as total FROM exam_requests WHERE status = 'pending'");
        return $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;
    }

    public function getPendingFinalAverageRequestsCount(): int
    {
        $conn = Database::getConnection();
        $check = $conn->query("SHOW TABLES LIKE 'final_average_requests'");
        if (!$check || $check->num_rows === 0) {
            return 0;
        }

        $result = $conn->query("SELECT COUNT(*) as total FROM final_average_requests WHERE status = 'pending'");
        return $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;
    }
}
