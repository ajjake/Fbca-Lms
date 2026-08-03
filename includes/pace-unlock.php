<?php
/**
 * PACE Unlock Logic
 * - Unlocks next PACE after completing current one
 * - Unlocks Monthly Test after completing 2 PACEs
 * - Unlocks Quarter Test after completing 3 PACEs
 * - Unlocks next quarter after passing Quarter Test
 */

function unlockNextPace($conn, $studentId, $completedLessonId) {
    // Get completed lesson details
    $stmt = $conn->prepare("SELECT subject_id, quarter, level, order_index, pace_type FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $completedLessonId);
    $stmt->execute();
    $lesson = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$lesson) return;
    
    $subjectId = $lesson['subject_id'];
    $quarter = $lesson['quarter'];
    $level = $lesson['level'];
    $orderIndex = $lesson['order_index'];
    $paceType = $lesson['pace_type'] ?? 'lesson';
    
    // If it's a regular lesson (PACE), unlock next PACE
    if ($paceType === 'lesson') {
        $completedPaces = $orderIndex + 1; // 0-indexed, so +1
        
        // Unlock next PACE if exists
        $nextPaceIndex = $orderIndex + 1;
        $stmt = $conn->prepare("
            SELECT id FROM lessons 
            WHERE subject_id = ? AND quarter = ? AND level = ? 
            AND order_index = ? AND pace_type = 'lesson'
        ");
        $stmt->bind_param("iiii", $subjectId, $quarter, $level, $nextPaceIndex);
        $stmt->execute();
        $nextPace = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($nextPace) {
            // Unlock next PACE
            $stmt = $conn->prepare("
                INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                VALUES (?, ?, 'unlocked', NOW())
                ON DUPLICATE KEY UPDATE status = 'unlocked', unlocked_at = NOW()
            ");
            $stmt->bind_param("ii", $studentId, $nextPace['id']);
            $stmt->execute();
            $stmt->close();
        }
        
        // Check if Monthly Test should be unlocked (after 2 PACEs)
        if ($completedPaces >= 2) {
            $stmt = $conn->prepare("
                SELECT id FROM lessons 
                WHERE subject_id = ? AND quarter = ? AND level = ? 
                AND pace_type = 'monthly_test'
            ");
            $stmt->bind_param("iii", $subjectId, $quarter, $level);
            $stmt->execute();
            $monthlyTest = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($monthlyTest) {
                $stmt = $conn->prepare("
                    INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                    VALUES (?, ?, 'unlocked', NOW())
                    ON DUPLICATE KEY UPDATE status = 'unlocked', unlocked_at = NOW()
                ");
                $stmt->bind_param("ii", $studentId, $monthlyTest['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // Check if Quarter Test should be unlocked (after 3 PACEs)
        if ($completedPaces >= 3) {
            $stmt = $conn->prepare("
                SELECT id FROM lessons 
                WHERE subject_id = ? AND quarter = ? AND level = ? 
                AND pace_type = 'quarter_test'
            ");
            $stmt->bind_param("iii", $subjectId, $quarter, $level);
            $stmt->execute();
            $quarterTest = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($quarterTest) {
                $stmt = $conn->prepare("
                    INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                    VALUES (?, ?, 'unlocked', NOW())
                    ON DUPLICATE KEY UPDATE status = 'unlocked', unlocked_at = NOW()
                ");
                $stmt->bind_param("ii", $studentId, $quarterTest['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    
    // If it's a Quarter Test and passed, unlock next quarter's first PACE
    if ($paceType === 'quarter_test') {
        // Check if quarter test was passed
        $stmt = $conn->prepare("
            SELECT passed FROM lesson_scores 
            WHERE student_id = ? AND lesson_id = ? 
            ORDER BY taken_at DESC LIMIT 1
        ");
        $stmt->bind_param("ii", $studentId, $completedLessonId);
        $stmt->execute();
        $scoreResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($scoreResult && $scoreResult['passed']) {
            // Unlock first PACE of next quarter
            $nextQuarter = $quarter + 1;
            if ($nextQuarter <= 4) {
                $stmt = $conn->prepare("
                    SELECT id FROM lessons 
                    WHERE subject_id = ? AND quarter = ? AND level = ? 
                    AND order_index = 0 AND pace_type = 'lesson'
                    ORDER BY id ASC LIMIT 1
                ");
                $stmt->bind_param("iii", $subjectId, $nextQuarter, $level);
                $stmt->execute();
                $nextQuarterPace = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($nextQuarterPace) {
                    $stmt = $conn->prepare("
                        INSERT INTO student_progress (student_id, lesson_id, status, unlocked_at)
                        VALUES (?, ?, 'unlocked', NOW())
                        ON DUPLICATE KEY UPDATE status = 'unlocked', unlocked_at = NOW()
                    ");
                    $stmt->bind_param("ii", $studentId, $nextQuarterPace['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

/**
 * Check if student can access a test
 */
function canAccessTest($conn, $studentId, $testLessonId) {
    $stmt = $conn->prepare("SELECT subject_id, quarter, level, pace_type FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $testLessonId);
    $stmt->execute();
    $test = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$test) return false;
    
    $requiredPaces = $test['pace_type'] === 'monthly_test' ? 2 : 3;
    
    // Count completed PACEs in this subject/quarter/level
    $stmt = $conn->prepare("
        SELECT COUNT(*) as completed_count
        FROM student_progress sp
        INNER JOIN lessons l ON sp.lesson_id = l.id
        WHERE sp.student_id = ? 
        AND l.subject_id = ? 
        AND l.quarter = ? 
        AND l.level = ?
        AND l.pace_type = 'lesson'
        AND sp.status = 'completed'
    ");
    $stmt->bind_param("iiii", $studentId, $test['subject_id'], $test['quarter'], $test['level']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return ($result['completed_count'] ?? 0) >= $requiredPaces;
}
?>
