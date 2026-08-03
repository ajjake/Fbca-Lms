<?php
// Grading system functions

function updateQuarterGrades($conn, $studentId, $lessonId) {
    // Get lesson details
    $stmt = $conn->prepare("SELECT subject_id, quarter, level FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $lessonId);
    $stmt->execute();
    $lesson = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$lesson) return;
    
    $subjectId = $lesson['subject_id'];
    $quarter = $lesson['quarter'];
    $level = $lesson['level'];
    
    // Get best scores for each lesson in this quarter and subject (use best attempt per lesson)
    $stmt = $conn->prepare("
        SELECT MAX(ls.percentage) as best_percentage, ls.lesson_id
        FROM lesson_scores ls
        INNER JOIN lessons l ON ls.lesson_id = l.id
        WHERE ls.student_id = ? AND l.subject_id = ? AND l.quarter = ? AND l.level = ?
        GROUP BY ls.lesson_id
    ");
    $stmt->bind_param("iiii", $studentId, $subjectId, $quarter, $level);
    $stmt->execute();
    $scores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Calculate lesson average (average of best scores for each lesson in this quarter/subject)
    $lessonAverage = 0;
    if (count($scores) > 0) {
        $total = 0;
        foreach ($scores as $score) {
            $total += $score['best_percentage'];
        }
        $lessonAverage = round($total / count($scores), 2);
    }
    
    // Get quarter exam score
    $stmt = $conn->prepare("
        SELECT percentage FROM quarter_exam_scores
        WHERE student_id = ? AND subject_id = ? AND quarter = ?
    ");
    $stmt->bind_param("iii", $studentId, $subjectId, $quarter);
    $stmt->execute();
    $examResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $quarterExamScore = $examResult ? round($examResult['percentage'], 2) : 0;
    
    // Calculate final quarter grade (average of lesson average and quarter exam)
    // Formula: (Lesson Average + Quarter Exam) / 2
    // If no quarter exam yet, use lesson average only
    if ($quarterExamScore > 0) {
        $finalGrade = round(($lessonAverage + $quarterExamScore) / 2, 2);
    } else {
        $finalGrade = $lessonAverage;
    }
    
    // Insert or update quarter grade
    $stmt = $conn->prepare("
        INSERT INTO quarter_grades (student_id, subject_id, quarter, lesson_average, quarter_exam_score, final_grade)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            lesson_average = VALUES(lesson_average),
            quarter_exam_score = VALUES(quarter_exam_score),
            final_grade = VALUES(final_grade)
    ");
    $stmt->bind_param("iiiddd", $studentId, $subjectId, $quarter, $lessonAverage, $quarterExamScore, $finalGrade);
    $stmt->execute();
    $stmt->close();
    
    // Update final grades
    updateFinalGrades($conn, $studentId, $subjectId);
}

function updateFinalGrades($conn, $studentId, $subjectId) {
    // Get all quarter grades
    $stmt = $conn->prepare("
        SELECT quarter, final_grade FROM quarter_grades
        WHERE student_id = ? AND subject_id = ?
    ");
    $stmt->bind_param("ii", $studentId, $subjectId);
    $stmt->execute();
    $quarters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $q1 = 0; $q2 = 0; $q3 = 0; $q4 = 0;
    
    foreach ($quarters as $q) {
        if ($q['quarter'] == 1) $q1 = $q['final_grade'];
        if ($q['quarter'] == 2) $q2 = $q['final_grade'];
        if ($q['quarter'] == 3) $q3 = $q['final_grade'];
        if ($q['quarter'] == 4) $q4 = $q['final_grade'];
    }
    
    // Calculate final average (average of all 4 quarters)
    // Average of each quarter per subject for the final average
    $quartersWithGrades = 0;
    $total = 0;
    if ($q1 > 0) { $total += $q1; $quartersWithGrades++; }
    if ($q2 > 0) { $total += $q2; $quartersWithGrades++; }
    if ($q3 > 0) { $total += $q3; $quartersWithGrades++; }
    if ($q4 > 0) { $total += $q4; $quartersWithGrades++; }
    
    // Final average = average of all quarters (only count quarters with grades > 0)
    $finalAverage = $quartersWithGrades > 0 ? round($total / $quartersWithGrades, 2) : 0;
    
    // Insert or update final grade
    $stmt = $conn->prepare("
        INSERT INTO final_grades (student_id, subject_id, q1_grade, q2_grade, q3_grade, q4_grade, final_average)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            q1_grade = VALUES(q1_grade),
            q2_grade = VALUES(q2_grade),
            q3_grade = VALUES(q3_grade),
            q4_grade = VALUES(q4_grade),
            final_average = VALUES(final_average)
    ");
    // 2 ints + 5 doubles = 7 params
    $stmt->bind_param("iiddddd", $studentId, $subjectId, $q1, $q2, $q3, $q4, $finalAverage);
    $stmt->execute();
    $stmt->close();
}
?>
