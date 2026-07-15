<?php

namespace App\Http\Controllers\Api;

use App\Models\MAttendanceLocation;
use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MProject;
use App\Models\MProjectHandle;
use App\Models\MSkillSet;
use App\Models\TrAchievement;
use App\Models\TrCalendarSharing;
use App\Models\TrEvaluation;
use App\Support\ProjectScoreboard;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends ApiController
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $interns = MIntern::where('bitActive', true)
            ->when(RoleAccess::isIntern($user), fn ($query) => $query->where('intIntern_ID', $user->intern?->intIntern_ID))
            ->when(! RoleAccess::isIntern($user), fn ($query) => $query->where(fn ($query) => RoleAccess::constrainDigitalisasiInterns($query)))
            ->get();
        $assignments = $interns->load(['projects.project.skillSet'])->pluck('projects')->flatten();
        $activeAssignments = $assignments->filter(fn ($assignment) => $assignment->bitActive && $assignment->project?->bitActive);
        $evaluations = TrEvaluation::where('bitActive', true)
            ->when(RoleAccess::isIntern($user), fn ($query) => $query->where('intIntern_ID', $user->intern?->intIntern_ID))
            ->when(! RoleAccess::isIntern($user), fn ($query) => $query->whereHas('intern', fn ($query) => RoleAccess::constrainDigitalisasiInterns($query)))
            ->orderByDesc('dtmEvaluationCompleted')
            ->get()
            ->unique('intIntern_ID');
        $upcoming = TrCalendarSharing::where('bitActive', true)
            ->whereDate('dtmCalendarSharingDate', '>=', now()->startOfDay())
            ->orderBy('dtmCalendarSharingDate')
            ->take(5)
            ->get();
        $scoreboard = ProjectScoreboard::rows();

        return $this->success([
            'period' => now()->format('Y-m'),
            'total_interns' => $interns->count(),
            'active_projects' => MProject::where('bitActive', true)->count(),
            'average_exposure_score' => round((float) $evaluations->avg('floatExposureScore'), 2),
            'average_progress' => round((float) $activeAssignments->avg('floatProgress'), 2),
            'achievements' => TrAchievement::where('bitActive', true)->count(),
            'project_type_counts' => $activeAssignments->groupBy(fn ($a) => $a->project?->txtProjectType ?: 'Other')->map->count(),
            'upcoming_calendar_sharings' => $upcoming->map(fn ($sharing) => $this->calendarSharing($sharing))->values(),
            'leaderboard' => $scoreboard->take(5)->values()->map(fn ($row, $index) => [
                'rank' => $index + 1,
                'intern' => $row['intern'] ? $this->internPayload($row['intern']) : null,
                'mentor' => $row['mentor'] ? ['id' => (int) $row['mentor']->intMentor_ID, 'name' => $row['mentor']->txtMentorName] : null,
                'main_project' => $row['main_project'],
                'score' => $row['score'],
                'breakdown' => [
                    'main' => $row['main'],
                    'collaboration' => $row['collaboration'],
                    'satellite' => $row['satellite'],
                    'sharing' => $row['sharing'],
                ],
            ])->values(),
        ], 'Ringkasan dashboard berhasil diambil.');
    }

    public function projects(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::can($user, 'projects'), 403, 'Kamu tidak memiliki akses ke project.');
        $query = MProject::with(['skillSet', 'stages', 'assignments.intern', 'assignments.mentor'])
            ->where('bitActive', true)
            ->when(RoleAccess::isDigitalisasiIntern($user), fn ($query) => $query->whereHas('assignments', fn ($query) => $query->where('intIntern_ID', $user->intern->intIntern_ID)->where('bitActive', true)))
            ->when($request->filled('type'), fn ($query) => $query->where('txtProjectType', $request->query('type')))
            ->when($request->filled('skill_set_id'), fn ($query) => $query->where('intSkillSet_ID', $request->integer('skill_set_id')))
            ->orderBy('txtProjectName');

        return $this->paginated(
            $query->paginate(min(100, max(1, $request->integer('per_page', 20)))),
            fn ($project) => $this->projectPayload($project),
            'Daftar project berhasil diambil.',
        );
    }

    public function projectDetail(Request $request, int $project): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::can($user, 'projects'), 403, 'Kamu tidak memiliki akses ke project.');
        $model = MProject::with(['skillSet', 'stages', 'assignments.intern', 'assignments.mentor'])->findOrFail($project);

        if (RoleAccess::isDigitalisasiIntern($user)
            && ! $model->assignments->contains(fn ($assignment) => (int) $assignment->intIntern_ID === (int) $user->intern->intIntern_ID && $assignment->bitActive)) {
            abort(403, 'Project ini bukan bagian dari internship kamu.');
        }

        return $this->success($this->projectPayload($model), 'Detail project berhasil diambil.');
    }

    public function calendarSharings(Request $request): JsonResponse
    {
        $query = TrCalendarSharing::with(['creator.intern', 'creator.mentor'])
            ->where('bitActive', true)
            ->when($request->filled('from'), fn ($query) => $query->whereDate('dtmCalendarSharingDate', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('dtmCalendarSharingDate', '<=', $request->query('to')))
            ->when($request->filled('status'), fn ($query) => $query->where('txtCalendarSharingStatus', $request->query('status')))
            ->orderBy('dtmCalendarSharingDate');

        return $this->paginated(
            $query->paginate(min(100, max(1, $request->integer('per_page', 20)))),
            fn ($sharing) => $this->calendarSharing($sharing),
            'Daftar calendar sharing berhasil diambil.',
        );
    }

    public function skillSets(Request $request): JsonResponse
    {
        $query = MSkillSet::where('bitActive', true)->orderBy('txtSkillSetName');

        return $this->paginated(
            $query->paginate(min(100, max(1, $request->integer('per_page', 50)))),
            fn ($skillSet) => [
                'id' => (int) $skillSet->intSkillSet_ID,
                'name' => $skillSet->txtSkillSetName,
                'description' => $skillSet->txtSkillSetDescription,
                'active' => (bool) $skillSet->bitActive,
            ],
            'Daftar skill set berhasil diambil.',
        );
    }

    public function projectHandles(Request $request): JsonResponse
    {
        abort_unless(RoleAccess::isHeadmaster($this->user($request)), 403, 'Hanya Headmaster yang dapat melihat konfigurasi project handle.');

        return $this->success([
            'weights' => ProjectScoreboard::weights(),
            'durations' => MProjectHandle::where('bitActive', true)->orderBy('txtProjectHandleDuration')->get()->map(fn ($handle) => [
                'id' => (int) $handle->intProjectHandle_ID,
                'duration' => $handle->txtProjectHandleDuration,
                'main' => (int) $handle->intProjectHandleMain,
                'collaboration' => (int) $handle->intProjectHandleCollaboration,
                'satellite' => (int) $handle->intProjectHandleSatellite,
                'sharing' => (int) $handle->intProjectHandleSharing,
                'active' => (bool) $handle->bitActive,
            ])->values(),
        ], 'Konfigurasi project handle berhasil diambil.');
    }

    public function interns(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::can($user, 'master-data') || RoleAccess::isMentor($user), 403, 'Kamu tidak memiliki akses ke data intern.');
        $query = MIntern::with('user')->where('bitActive', true)->where(fn ($query) => RoleAccess::constrainDigitalisasiInterns($query));

        return $this->paginated(
            $query->when($request->filled('search'), fn ($query) => $query->where('txtInternName', 'like', '%'.$request->query('search').'%'))
                ->orderBy('txtInternName')
                ->paginate(min(100, max(1, $request->integer('per_page', 20)))),
            fn ($intern) => $this->internPayload($intern),
            'Daftar intern berhasil diambil.',
        );
    }

    public function internDetail(Request $request, int $intern): JsonResponse
    {
        $user = $this->user($request);
        $model = MIntern::with(['user', 'projects.project', 'projects.mentor', 'evaluations', 'achievements'])->findOrFail($intern);
        $isOwner = RoleAccess::isIntern($user) && (int) $user->intern->intIntern_ID === $intern;
        abort_unless($isOwner || RoleAccess::can($user, 'master-data') || RoleAccess::isMentor($user), 403, 'Kamu tidak memiliki akses ke profil intern ini.');

        return $this->success([
            ...$this->internPayload($model),
            'projects' => $model->projects->map(fn ($assignment) => [
                'id' => (int) $assignment->intInternProject_ID,
                'project' => $assignment->project ? $this->projectPayload($assignment->project, false) : null,
                'mentor' => $assignment->mentor ? ['id' => (int) $assignment->mentor->intMentor_ID, 'name' => $assignment->mentor->txtMentorName] : null,
                'progress' => $assignment->floatProgress,
                'status' => $assignment->txtStatus,
            ])->values(),
            'evaluations' => $model->evaluations->map(fn ($evaluation) => $this->evaluation($evaluation))->values(),
            'achievements' => $model->achievements->map(fn ($achievement) => $this->achievement($achievement))->values(),
        ], 'Detail intern berhasil diambil.');
    }

    public function mentors(Request $request): JsonResponse
    {
        $query = MMentor::where('bitActive', true)->orderBy('txtMentorName');

        return $this->paginated(
            $query->paginate(min(100, max(1, $request->integer('per_page', 20)))),
            fn ($mentor) => [
                'id' => (int) $mentor->intMentor_ID,
                'user_id' => (int) $mentor->intUser_ID,
                'name' => $mentor->txtMentorName,
                'gender' => $mentor->txtMentorGender,
                'department' => $mentor->txtDepartment,
                'role' => $mentor->txtRole,
            ],
            'Daftar mentor berhasil diambil.',
        );
    }

    public function achievements(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::can($user, 'achievements') || RoleAccess::isIntern($user), 403, 'Kamu tidak memiliki akses ke achievement.');
        $query = TrAchievement::with('intern')->where('bitActive', true)
            ->when(RoleAccess::isIntern($user), fn ($query) => $query->where('intIntern_ID', $user->intern->intIntern_ID))
            ->orderByDesc('dtmAwarded');

        return $this->paginated(
            $query->paginate(min(100, max(1, $request->integer('per_page', 20)))),
            fn ($achievement) => $this->achievementPayload($achievement),
            'Daftar achievement berhasil diambil.',
        );
    }

    public function evaluations(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::can($user, 'analytics'), 403, 'Kamu tidak memiliki akses ke evaluasi.');
        $query = TrEvaluation::with(['intern', 'evaluator'])->where('bitActive', true)
            ->when(RoleAccess::isIntern($user), fn ($query) => $query->where('intIntern_ID', $user->intern->intIntern_ID))
            ->when($request->filled('intern_id'), fn ($query) => $query->where('intIntern_ID', $request->integer('intern_id')))
            ->orderByDesc('dtmEvaluationCompleted');

        return $this->paginated(
            $query->paginate(min(100, max(1, $request->integer('per_page', 20)))),
            fn ($evaluation) => $this->evaluation($evaluation),
            'Daftar evaluasi berhasil diambil.',
        );
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::can($user, 'leaderboard'), 403, 'Kamu tidak memiliki akses ke leaderboard.');
        $rows = ProjectScoreboard::rows();

        return $this->success([
            'weights' => ProjectScoreboard::weights(),
            'items' => $rows->map(fn ($row, $index) => [
                'rank' => $index + 1,
                'intern' => $row['intern'] ? $this->internPayload($row['intern']) : null,
                'mentor' => $row['mentor'] ? ['id' => (int) $row['mentor']->intMentor_ID, 'name' => $row['mentor']->txtMentorName] : null,
                'main_project' => $row['main_project'],
                'score' => $row['score'],
                'period' => $row['period'],
                'breakdown' => [
                    'main' => $row['main'],
                    'collaboration' => $row['collaboration'],
                    'satellite' => $row['satellite'],
                    'sharing' => $row['sharing'],
                ],
            ])->values(),
        ], 'Leaderboard berhasil diambil.');
    }

    public function attendanceLocations(Request $request): JsonResponse
    {
        abort_unless(RoleAccess::isAttendanceAdmin($this->user($request)), 403, 'Hanya admin absensi yang dapat melihat lokasi kantor.');

        return $this->success(MAttendanceLocation::where('bitActive', true)->orderBy('txtAttendanceLocationName')->get()->map(fn ($location) => [
            'id' => (int) $location->intAttendanceLocation_ID,
            'code' => $location->txtAttendanceLocationCode,
            'name' => $location->txtAttendanceLocationName,
            'address' => $location->txtAttendanceLocationAddress,
            'latitude' => $location->floatAttendanceLocationLatitude,
            'longitude' => $location->floatAttendanceLocationLongitude,
            'radius_meter' => $location->intAttendanceLocationRadiusMeter,
            'tolerance_meter' => $location->intAttendanceLocationToleranceMeter,
            'maximum_accuracy_meter' => $location->intAttendanceLocationMaximumAccuracyMeter,
        ])->values(), 'Daftar lokasi absensi berhasil diambil.');
    }

    protected function calendarSharing(mixed $sharing): array
    {
        $sharing->loadMissing(['creator.intern', 'creator.mentor']);

        return [
            'id' => (int) $sharing->intCalendarSharing_ID,
            'theme' => $sharing->txtCalendarSharingTheme,
            'objective' => $sharing->txtCalendarSharingObjective,
            'description' => $sharing->txtCalendarSharingDescription,
            'target_audience' => $sharing->txtCalendarSharingTargetAudience,
            'date' => $sharing->dtmCalendarSharingDate?->toISOString(),
            'status' => $sharing->txtCalendarSharingStatus,
            'icon' => $sharing->txtCalendarSharingIcon,
            'creator' => $this->person($sharing->creator),
        ];
    }
}
