<?php

namespace App\Application;

use App\Core\Database;

final class TeacherExamRequestService
{
    public function getRequests(int $teacherId, string $filter = 'all'): array
    {
        $conn = Database::getConnection();

        $sql = 
            'SELECT er.*, u.name as student_name, u.level as student_level, '
            . 'l.title as lesson_title, l.lesson_number, s.name as subject_name, reviewer.name as reviewer_name '
            . 'FROM exam_requests er '
            . 'INNER JOIN users u ON er.student_id = u.id '
            . 'INNER JOIN lessons l ON er.lesson_id = l.id '
            . 'INNER JOIN subjects s ON l.subject_id = s.id '
            . 'INNER JOIN teacher_subjects ts ON l.subject_id = ts.subject_id '
            . 'LEFT JOIN users reviewer ON er.reviewed_by = reviewer.id '
            . 'WHERE ts.teacher_id = ? ';

        if ($filter === 'pending') {
            $sql .= "AND er.status = 'pending' ";
        } elseif ($filter === 'approved') {
            $sql .= "AND er.status = 'approved' ";
        } elseif ($filter === 'denied') {
            $sql .= "AND er.status = 'denied' ";
        }

        $sql .= 'ORDER BY er.requested_at DESC';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $teacherId);
        $stmt->execute();
        $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $requests;
    }

    public function processRequest(int $teacherId, int $requestId, string $action, string $remarks = ''): bool
    {
        $conn = Database::getConnection();

        $stmt = $conn->prepare(
            'SELECT er.* FROM exam_requests er '
            . 'INNER JOIN lessons l ON er.lesson_id = l.id '
            . 'INNER JOIN teacher_subjects ts ON l.subject_id = ts.subject_id '
            . 'WHERE er.id = ? AND ts.teacher_id = ?'
        );
        $stmt->bind_param('ii', $requestId, $teacherId);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) {
            return false;
        }

        $status = $action === 'approve' ? 'approved' : 'denied';
        $stmt = $conn->prepare(
            'UPDATE exam_requests SET status = ?, remarks = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?'
        );
        $stmt->bind_param('ssii', $status, $remarks, $teacherId, $requestId);
        $stmt->execute();
        $stmt->close();

        if ($status === 'approved') {
            $stmt = $conn->prepare(
                'INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at) '
                . 'VALUES (?, ?, "unlocked", NOW()) '
                . 'ON DUPLICATE KEY UPDATE status = VALUES(status), unlocked_at = NOW()'
            );
            $stmt->bind_param('ii', $request['student_id'], $request['lesson_id']);
            $stmt->execute();
            $stmt->close();
        }

        return true;
    }
}
