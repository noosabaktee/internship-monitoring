<?php

namespace App\Http\Controllers\Api;

use App\Models\MIntern;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;

class InternshipReminderController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        $today = now('Asia/Jakarta')->startOfDay();
        $until = $today->copy()->addMonthNoOverflow();

        // Extensions are stored as JSON. Use the model's effective date so the
        // reminder follows the same completion rules on every database driver.
        $interns = MIntern::query()
            ->with('user:intUser_ID,txtEmail')
            ->where('bitActive', true)
            ->whereHas('user', fn ($query) => $query
                ->where('bitActive', true)
                ->where('txtRole', RoleAccess::ROLE_INTERN))
            ->get([
                'intIntern_ID', 'intUser_ID', 'txtInternNo', 'txtInternName',
                'txtUniversity', 'txtDept', 'txtInternType', 'dtmEndDate',
                'txtInternExtendEndDates',
            ])
            ->map(function (MIntern $intern) use ($today, $until): ?array {
                $endDate = $intern->effectiveEndDate();

                if ($endDate === null || ! $endDate->betweenIncluded($today, $until)) {
                    return null;
                }

                return [
                    'id' => (int) $intern->intIntern_ID,
                    'user_id' => (int) $intern->intUser_ID,
                    'number' => $intern->txtInternNo,
                    'name' => $intern->txtInternName,
                    'email' => $intern->user->txtEmail,
                    'university' => $intern->txtUniversity,
                    'department' => $intern->txtDept,
                    'type' => RoleAccess::normalizedInternType($intern->txtInternType),
                    'end_date' => $intern->dtmEndDate?->toDateString(),
                    'effective_end_date' => $endDate->toDateString(),
                    'remaining_days' => (int) $today->diffInDays($endDate),
                ];
            })
            ->filter()
            ->sortBy([['effective_end_date', 'asc'], ['id', 'asc']])
            ->values();

        return $this->success($interns, 'Daftar intern yang akan selesai dalam satu bulan berhasil diambil.', [
            'from_date' => $today->toDateString(),
            'to_date' => $until->toDateString(),
            'timezone' => 'Asia/Jakarta',
            'total' => $interns->count(),
        ]);
    }
}
