<?php

namespace App\Support\Enums;

class StudyStatus
{
    public const STUDENT_ACTIVE = 'dang_hoc';
    public const STUDENT_DEFERRED = 'bao_luu';
    public const STUDENT_GRADUATED = 'tot_nghiep';
    public const STUDENT_DROPPED = 'thoi_hoc';
    public const STUDENT_SUSPENDED = 'dinh_chi';

    public const STAFF_ACTIVE = 'dang_cong_tac';
    public const STAFF_ON_LEAVE = 'nghi_phep';
    public const STAFF_RETIRED = 'nghi_huu';

    public static function studentStatuses(): array
    {
        return [
            self::STUDENT_ACTIVE,
            self::STUDENT_DEFERRED,
            self::STUDENT_GRADUATED,
            self::STUDENT_DROPPED,
            self::STUDENT_SUSPENDED,
        ];
    }

    public static function staffStatuses(): array
    {
        return [
            self::STAFF_ACTIVE,
            self::STAFF_ON_LEAVE,
            self::STAFF_RETIRED,
        ];
    }

    public static function all(): array
    {
        return array_merge(self::studentStatuses(), self::staffStatuses());
    }

    public static function labels(): array
    {
        return [
            self::STUDENT_ACTIVE => 'Đang học',
            self::STUDENT_DEFERRED => 'Bảo lưu',
            self::STUDENT_GRADUATED => 'Tốt nghiệp',
            self::STUDENT_DROPPED => 'Thôi học',
            self::STUDENT_SUSPENDED => 'Đình chỉ',
            self::STAFF_ACTIVE => 'Đang công tác',
            self::STAFF_ON_LEAVE => 'Nghỉ phép',
            self::STAFF_RETIRED => 'Nghỉ hưu',
        ];
    }
}

