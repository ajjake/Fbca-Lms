<?php

namespace App\Application;

use App\Core\Database;

final class FinalAverageRequestService
{
    public function submitRequest(int $studentId, int $quarter): array
    {
        $conn = Database::getConnection();
        $this->ensureTableExists($conn);

        $stmt = $conn->prepare(
            'SELECT status FROM final_average_requests WHERE student_id = ? AND quarter = ? ORDER BY requested_at DESC LIMIT 1'
        );
        $stmt->bind_param('ii', $studentId, $quarter);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing && $existing['status'] === 'pending') {
            return ['success' => false, 'message' => 'You already have a pending request for this quarter'];
        }

        if ($existing && $existing['status'] === 'approved') {
            return ['success' => false, 'message' => 'Your final average for this quarter has already been approved'];
        }

        $stmt = $conn->prepare(
            'INSERT INTO final_average_requests (student_id, quarter, status) VALUES (?, ?, "pending")'
        );
        $stmt->bind_param('ii', $studentId, $quarter);
        $result = $stmt->execute();
        $stmt->close();

        return [
            'success' => (bool) $result,
            'message' => $result ? 'Final average request submitted successfully' : 'Failed to submit request',
        ];
    }

    private function ensureTableExists(\mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS final_average_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            quarter INT NOT NULL,
            status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
            remarks TEXT,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL,
            reviewed_by INT NULL,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_student_quarter (student_id, quarter)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
}
