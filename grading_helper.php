<?php
/**
 * Thamani High School - Academic Grading & Ranking Engine
 * --------------------------------------------------------
 * Computes custom grade boundaries, points, remarks, and stream rankings.
 */

require_once 'conn.php';

function get_grading_scales_from_db($educationLevel = 'O-Level') {
    global $conn;
    static $cache = [];
    if (isset($cache[$educationLevel])) return $cache[$educationLevel];

    $scales = [];
    $stmt = thamani_db_prepare($conn, "SELECT min_score, max_score, grade, points, remark FROM grading_scales WHERE education_level = ? ORDER BY min_score DESC");
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, "s", $educationLevel);
        thamani_db_stmt_execute($stmt);
        $res = thamani_db_stmt_get_result($stmt);
        if ($res) {
            while ($r = thamani_db_fetch_assoc($res)) {
                $scales[] = [
                    'min'    => floatval($r['min_score']),
                    'max'    => floatval($r['max_score']),
                    'grade'  => trim($r['grade']),
                    'points' => (int)$r['points'],
                    'remark' => trim($r['remark'])
                ];
            }
        }
        thamani_db_stmt_close($stmt);
    }

    // Default Ugandan UNEB Fallback Scale if DB table is empty
    if (empty($scales)) {
        if (strtoupper($educationLevel) === 'A-LEVEL') {
            $scales = [
                ['min' => 80, 'max' => 100, 'grade' => 'A', 'points' => 6, 'remark' => 'Principal Pass A'],
                ['min' => 70, 'max' => 79.99, 'grade' => 'B', 'points' => 5, 'remark' => 'Principal Pass B'],
                ['min' => 60, 'max' => 69.99, 'grade' => 'C', 'points' => 4, 'remark' => 'Principal Pass C'],
                ['min' => 50, 'max' => 59.99, 'grade' => 'D', 'points' => 3, 'remark' => 'Principal Pass D'],
                ['min' => 40, 'max' => 49.99, 'grade' => 'E', 'points' => 2, 'remark' => 'Principal Pass E'],
                ['min' => 35, 'max' => 39.99, 'grade' => 'O', 'points' => 1, 'remark' => 'Subsidiary Pass'],
                ['min' => 0,  'max' => 34.99, 'grade' => 'F', 'points' => 0, 'remark' => 'Fail']
            ];
        } else {
            // O-Level UNEB Standard D1 - F9
            $scales = [
                ['min' => 80, 'max' => 100, 'grade' => 'D1', 'points' => 1, 'remark' => 'Distinction 1'],
                ['min' => 75, 'max' => 79.99, 'grade' => 'D2', 'points' => 2, 'remark' => 'Distinction 2'],
                ['min' => 68, 'max' => 74.99, 'grade' => 'C3', 'points' => 3, 'remark' => 'Credit 3'],
                ['min' => 60, 'max' => 67.99, 'grade' => 'C4', 'points' => 4, 'remark' => 'Credit 4'],
                ['min' => 55, 'max' => 59.99, 'grade' => 'C5', 'points' => 5, 'remark' => 'Credit 5'],
                ['min' => 50, 'max' => 54.99, 'grade' => 'C6', 'points' => 6, 'remark' => 'Credit 6'],
                ['min' => 45, 'max' => 49.99, 'grade' => 'P7', 'points' => 7, 'remark' => 'Pass 7'],
                ['min' => 40, 'max' => 44.99, 'grade' => 'P8', 'points' => 8, 'remark' => 'Pass 8'],
                ['min' => 0,  'max' => 39.99, 'grade' => 'F9', 'points' => 9, 'remark' => 'Fail 9']
            ];
        }
    }

    $cache[$educationLevel] = $scales;
    return $scales;
}

function calculate_grade_info($score, $maxScore = 100, $educationLevel = 'O-Level') {
    $percent = ($maxScore > 0) ? ($score / $maxScore) * 100 : 0;
    $scales  = get_grading_scales_from_db($educationLevel);

    foreach ($scales as $s) {
        if ($percent >= $s['min'] && $percent <= $s['max']) {
            return [
                'percent' => round($percent, 1),
                'grade'   => $s['grade'],
                'points'  => $s['points'],
                'remark'  => $s['remark']
            ];
        }
    }

    return [
        'percent' => round($percent, 1),
        'grade'   => 'F9',
        'points'  => 9,
        'remark'  => 'Fail 9'
    ];
}

/**
 * Calculates rank positions for a list of student totals.
 * Returns array mapping student_id => ordinal position string (e.g., '1st', '2nd', '3rd', '4th out of 32').
 */
function calculate_stream_ranks($studentTotalsMap) {
    // Sort descending by total score
    arsort($studentTotalsMap);

    $ranks = [];
    $currentRank = 1;
    $previousScore = null;
    $sameRankCount = 0;
    $totalStudents = count($studentTotalsMap);

    foreach ($studentTotalsMap as $sId => $totScore) {
        if ($previousScore !== null && abs($totScore - $previousScore) < 0.001) {
            $sameRankCount++;
        } else {
            $currentRank += $sameRankCount;
            $sameRankCount = 1;
            $previousScore = $totScore;
        }

        $suffix = 'th';
        if ($currentRank % 100 < 11 || $currentRank % 100 > 13) {
            switch ($currentRank % 10) {
                case 1: $suffix = 'st'; break;
                case 2: $suffix = 'nd'; break;
                case 3: $suffix = 'rd'; break;
            }
        }
        $ranks[$sId] = "{$currentRank}{$suffix} out of {$totalStudents}";
    }

    return $ranks;
}
