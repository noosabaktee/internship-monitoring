<?php

namespace App\Http\Controllers;

use App\Models\TrCalendarSharing;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CalendarSharingController extends Controller
{
    private const STATUSES = ['Open', 'Complete', 'Cancel', 'Reschedule'];

    public function index(Request $request): View
    {
        $month = $this->monthFromRequest($request);
        $calendarStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $calendarDays = collect();

        for ($date = $calendarStart->copy(); $date->lte($calendarEnd); $date->addDay()) {
            $calendarDays->push($date->copy());
        }

        $calendarSharings = TrCalendarSharing::with(['creator.intern', 'creator.mentor'])
            ->where('bitActive', true)
            ->orderBy('dtmCalendarSharingDate')
            ->orderBy('intCalendarSharing_ID')
            ->get();
        $eventsByDate = $calendarSharings
            ->filter(fn ($sharing) => $sharing->dtmCalendarSharingDate)
            ->groupBy(fn ($sharing) => $sharing->dtmCalendarSharingDate->format('Y-m-d'));

        return view('dashboard.calendar-sharing', [
            'calendarSharings' => $calendarSharings,
            'eventsByDate' => $eventsByDate,
            'calendarDays' => $calendarDays,
            'calendarMonth' => $month,
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->index($request)->with('mode', 'create');
    }

    public function store(Request $request, NotificationService $notifications): RedirectResponse
    {
        $validated = $this->validatedSharing($request);
        $now = now();

        $sharing = TrCalendarSharing::create([
            'intCalendarSharingCreatorUser_ID' => $request->session()->get('auth_user_id'),
            'txtCalendarSharingTheme' => $validated['txtCalendarSharingTheme'],
            'txtCalendarSharingObjective' => $validated['txtCalendarSharingObjective'] ?? null,
            'txtCalendarSharingDescription' => $validated['txtCalendarSharingDescription'] ?? null,
            'txtCalendarSharingTargetAudience' => $validated['txtCalendarSharingTargetAudience'] ?? null,
            'dtmCalendarSharingDate' => $validated['dtmCalendarSharingDate'],
            'txtCalendarSharingStatus' => $validated['txtCalendarSharingStatus'] ?? 'Open',
            'txtCalendarSharingIcon' => $validated['txtCalendarSharingIcon'] ?? 'fa-solid fa-people-group',
            'bitActive' => true,
            'txtInsertedBy' => 'system',
            'dtmInserted' => $now,
        ]);

        $notifications->calendarSharingCreated($sharing);

        return redirect()->route('calendar-sharing.index')->with('success', 'Calendar sharing data has been added.');
    }

    public function show(string $calendarSharing): View
    {
        $calendarSharing = TrCalendarSharing::with(['creator.intern', 'creator.mentor'])->findOrFail($calendarSharing);

        return view('dashboard.calendar-sharing', compact('calendarSharing'));
    }

    public function edit(Request $request, string $calendarSharing): View
    {
        $view = $this->index($request);
        $view->with('editingCalendarSharing', TrCalendarSharing::findOrFail($calendarSharing));
        $view->with('mode', 'edit');

        return $view;
    }

    public function update(Request $request, string $calendarSharing): RedirectResponse
    {
        $calendarSharingModel = TrCalendarSharing::findOrFail($calendarSharing);
        $validated = $this->validatedSharing($request);

        $calendarSharingModel->update([
            'txtCalendarSharingTheme' => $validated['txtCalendarSharingTheme'],
            'txtCalendarSharingObjective' => $validated['txtCalendarSharingObjective'] ?? null,
            'txtCalendarSharingDescription' => $validated['txtCalendarSharingDescription'] ?? null,
            'txtCalendarSharingTargetAudience' => $validated['txtCalendarSharingTargetAudience'] ?? null,
            'dtmCalendarSharingDate' => $validated['dtmCalendarSharingDate'],
            'txtCalendarSharingStatus' => $validated['txtCalendarSharingStatus'] ?? 'Open',
            'txtCalendarSharingIcon' => $validated['txtCalendarSharingIcon'] ?? 'fa-solid fa-people-group',
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('calendar-sharing.index')->with('success', 'Calendar sharing data has been updated.');
    }

    public function destroy(string $calendarSharing): RedirectResponse
    {
        $calendarSharingModel = TrCalendarSharing::findOrFail($calendarSharing);
        $calendarSharingModel->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('calendar-sharing.index')->with('success', 'Calendar sharing data has been deactivated.');
    }

    private function validatedSharing(Request $request): array
    {
        return $request->validate([
            'txtCalendarSharingTheme' => ['required', 'string', 'max:255'],
            'txtCalendarSharingObjective' => ['nullable', 'string', 'max:255'],
            'txtCalendarSharingDescription' => ['nullable', 'string', 'max:255'],
            'txtCalendarSharingTargetAudience' => ['nullable', 'string', 'max:255'],
            'dtmCalendarSharingDate' => ['required', 'date'],
            'txtCalendarSharingStatus' => ['nullable', Rule::in(self::STATUSES)],
            'txtCalendarSharingIcon' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function monthFromRequest(Request $request): Carbon
    {
        $month = $request->query('month', now()->format('Y-m'));

        try {
            return Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
