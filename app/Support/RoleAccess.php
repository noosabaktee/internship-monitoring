<?php

namespace App\Support;

use App\Models\MUser;

class RoleAccess
{
    public const ROLE_INTERN = 'Intern';

    public const ROLE_MENTOR = 'Mentor';

    public const ROLE_HEADMASTER = 'Headmaster';

    public const ROLE_HRD = 'HRD';

    public const INTERN_DIGITALISASI = 'digitalisasi';

    public const INTERN_REGULAR = 'regular';

    public const INTERN_PKL = 'pkl';

    /**
     * @return array<int, string>
     */
    public static function internTypes(): array
    {
        return [
            self::INTERN_DIGITALISASI,
            self::INTERN_REGULAR,
            self::INTERN_PKL,
        ];
    }

    public static function isHeadmaster(MUser $user): bool
    {
        return $user->txtRole === self::ROLE_HEADMASTER;
    }

    public static function isHrd(MUser $user): bool
    {
        return $user->txtRole === self::ROLE_HRD;
    }

    public static function isMentor(MUser $user): bool
    {
        return $user->txtRole === self::ROLE_MENTOR;
    }

    public static function isIntern(MUser $user): bool
    {
        return $user->txtRole === self::ROLE_INTERN && (bool) $user->intern;
    }

    public static function isAttendanceAdmin(MUser $user): bool
    {
        return self::isHeadmaster($user) || self::isHrd($user);
    }

    public static function internType(MUser $user): string
    {
        $type = self::normalizedInternType($user->intern?->txtInternType);

        return in_array($type, self::internTypes(), true) ? $type : self::INTERN_DIGITALISASI;
    }

    public static function isDigitalisasiIntern(MUser $user): bool
    {
        return self::isIntern($user) && self::internType($user) === self::INTERN_DIGITALISASI;
    }

    public static function normalizedInternType(?string $type): string
    {
        return strtolower((string) ($type ?: self::INTERN_DIGITALISASI));
    }

    public static function constrainDigitalisasiInterns($query): void
    {
        $query->where(function ($query) {
            $query->where('txtInternType', self::INTERN_DIGITALISASI)
                ->orWhereNull('txtInternType');
        });
    }

    public static function can(MUser $user, string $ability): bool
    {
        return match ($ability) {
            'dashboard' => true,
            'dashboard-sidebar' => true,
            'projects' => self::isMentor($user) || self::isHeadmaster($user) || self::isDigitalisasiIntern($user),
            'crud-projects' => self::isMentor($user) || self::isHeadmaster($user),
            'calendar-sharing' => true,
            'crud-calendar-sharing' => self::isMentor($user) || self::isHeadmaster($user) || self::isHrd($user),
            'attendance' => self::isIntern($user) || self::isAttendanceAdmin($user),
            'attendance-admin' => self::isAttendanceAdmin($user),
            'leaderboard' => self::isMentor($user) || self::isHeadmaster($user) || self::isHrd($user) || self::isDigitalisasiIntern($user),
            'exposure' => self::isMentor($user) || self::isHeadmaster($user) || self::isHrd($user) || self::isDigitalisasiIntern($user),
            'analytics' => self::isIntern($user) || self::isMentor($user) || self::isHeadmaster($user) || self::isHrd($user),
            'crud-analytics' => self::isMentor($user) || self::isHeadmaster($user),
            'work-from-home' => self::isIntern($user) || self::isAttendanceAdmin($user),
            'work-from-home-admin' => self::isAttendanceAdmin($user),
            'achievements' => self::isMentor($user) || self::isHeadmaster($user) || self::isHrd($user),
            'crud-achievements' => self::isMentor($user) || self::isHeadmaster($user),
            'reports' => self::isMentor($user) || self::isHeadmaster($user) || self::isDigitalisasiIntern($user),
            'settings' => self::isHeadmaster($user) || self::isHrd($user),
            'master-data' => self::isHeadmaster($user) || self::isHrd($user),
            'hrd-data' => self::isHeadmaster($user) || self::isHrd($user),
            'project-handles' => self::isHeadmaster($user),
            default => false,
        };
    }
}
