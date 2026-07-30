<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cố vấn học tập — rule điểm thấp
    |--------------------------------------------------------------------------
    |
    | Môn được coi là "điểm thấp" khi thỏa ít nhất một điều kiện:
    | - Tuyệt đối: final_score < absolute_low_score (thang 10)
    | - Tương đối: final_score < GPA_thang_10 − relative_gpa_delta
    |
    | GPA dùng cho rule tương đối là điểm trung bình có trọng số tín chỉ
    | trên thang 10 (không phải GPA 4.0).
    |
    */
    'study_advisor' => [
        'absolute_low_score' => (float) env('STUDY_ADVISOR_ABSOLUTE_LOW', 6.5),
        'relative_gpa_delta' => (float) env('STUDY_ADVISOR_GPA_DELTA', 1.0),
    ],

];
