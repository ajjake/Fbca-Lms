<?php

namespace App\Application;

use App\Core\Database;

final class ExamRequestService
{
    public function submitRequest(int $studentId, int $lessonId, string $requestType, ?int $quarter = null): array
    {
        $conn = Database::getConnection();

        $stmt = $conn->prepare('SELECT * FROM exam_requests WHERE student_id = ? AND lesson_id = ? AND request_type = ?');
        $stmt->bind_param('iis', $studentId, $lessonId, $requestType);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing && $existing['status'] === 'pending') {
            return ['success' => false, 'message' => 'You already have a pending request for this exam'];
        }

        $stmt = $conn->prepare(
            'INSERT INTO exam_requests (student_id, lesson_id, request_type, quarter, status) VALUES (?, ?, ?, ?, "pending")'
        );
        $stmt->bind_param('iisi', $studentId, $lessonId, $requestType, $quarter);
        $result = $stmt->execute();
        $stmt->close();

        return [
            'success' => (bool) $result,
            'message' => $result ? 'Exam request submitted successfully' : 'Failed to submit request',
        ];
    }
}
