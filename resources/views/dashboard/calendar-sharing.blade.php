@extends('layouts.app', [
    'title' => 'Calendar Sharing - Kalbe Internship Dashboard',
    'pageTitle' => 'CALENDAR SHARING',
    'pageSubtitle' => 'Manage planned sharing activities and calendar highlights.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingCalendarSharing)
        ? route('calendar-sharing.update', $editingCalendarSharing->intCalendarSharing_ID)
        : route('calendar-sharing.store');
    $authUser = \App\Models\MUser::with('intern')->find(session('auth_user_id'));
    $canManageSharing = $authUser && \App\Support\RoleAccess::can($authUser, 'crud-calendar-sharing');
    $sharingIcons = [
        ['value' => 'fa-solid fa-people-group', 'label' => 'Group'],
        ['value' => 'fa-solid fa-person-chalkboard', 'label' => 'Workshop'],
        ['value' => 'fa-solid fa-comments', 'label' => 'Discussion'],
        ['value' => 'fa-solid fa-book-open', 'label' => 'Learning'],
        ['value' => 'fa-solid fa-lightbulb', 'label' => 'Idea'],
        ['value' => 'fa-solid fa-microphone', 'label' => 'Talk'],
        ['value' => 'fa-solid fa-laptop-code', 'label' => 'Tech'],
        ['value' => 'fa-solid fa-network-wired', 'label' => 'Network'],
        ['value' => 'fa-solid fa-robot', 'label' => 'Automation'],
        ['value' => 'fa-solid fa-brain', 'label' => 'AI'],
        ['value' => 'fa-solid fa-gears', 'label' => 'Engineering'],
        ['value' => 'fa-solid fa-handshake-angle', 'label' => 'Mentoring'],
    ];
    $selectedIcon = old('txtCalendarSharingIcon', $editingCalendarSharing->txtCalendarSharingIcon ?? 'fa-solid fa-people-group');
    $creatorName = fn ($user) => $user?->intern?->txtInternName ?? $user?->mentor?->txtMentorName ?? $user?->txtEmail ?? '-';
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Calendar Sharing</h2>
            <p>Plan and monitor sharing sessions with calendar highlights.</p>
        </div>
        @if ($canManageSharing)
            <a class="btn btn-primary btn-add" href="{{ route('calendar-sharing.create', ['month' => $calendarMonth->format('Y-m')]) }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add Sharing</a>
        @endif
    </div>

    <div class="calendar-sharing-grid">
        <section class="profile-card">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>Sharing List</h2>
                    <p>Creator, date, status, and activity theme.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table sharing-table">
                    <thead>
                        <tr><th>Theme</th><th>Date</th><th>Status</th><th>Creator</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($calendarSharings as $sharing)
                            <tr>
                                <td>
                                    <strong><i class="{{ $sharing->txtCalendarSharingIcon ?: 'fa-solid fa-people-group' }}"></i> {{ $sharing->txtCalendarSharingTheme }}</strong><br>
                                    <span style="color:var(--text-gray); font-size:11px;">{{ $sharing->txtCalendarSharingTargetAudience ?: '-' }}</span>
                                </td>
                                <td>{{ $sharing->dtmCalendarSharingDate?->format('d M Y') ?? '-' }}</td>
                                <td><span class="status-badge {{ $sharing->txtCalendarSharingStatus === 'Cancel' ? 'status-inactive' : 'status-active' }}">{{ $sharing->txtCalendarSharingStatus ?: 'Open' }}</span></td>
                                <td>{{ $creatorName($sharing->creator) }}</td>
                                <td>
                                    @if ($canManageSharing)
                                        <div class="action-btns">
                                            <a class="btn-icon btn-edit" href="{{ route('calendar-sharing.edit', [$sharing->intCalendarSharing_ID, 'month' => $calendarMonth->format('Y-m')]) }}"><i class="fa-solid fa-pen"></i></a>
                                            <button
                                                class="btn-icon btn-delete"
                                                type="button"
                                                data-delete-modal-trigger
                                                data-delete-action="{{ route('calendar-sharing.destroy', $sharing->intCalendarSharing_ID) }}"
                                                data-delete-title="Deactivate Sharing Activity?"
                                                data-delete-message="{{ $sharing->txtCalendarSharingTheme }} will be marked inactive and removed from the active calendar."
                                                data-delete-submit="Deactivate"
                                            ><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="center">No sharing activity yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="profile-card">
            <div class="calendar-head">
                <a class="btn btn-light btn-sm" href="{{ route('calendar-sharing.index', ['month' => $previousMonth]) }}"><i class="fa-solid fa-chevron-left"></i></a>
                <div class="profile-card-title">
                    <h2>{{ $calendarMonth->format('F Y') }}</h2>
                    <p>Red highlight means the activity date has passed.</p>
                </div>
                <a class="btn btn-light btn-sm" href="{{ route('calendar-sharing.index', ['month' => $nextMonth]) }}"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
            <div class="sharing-calendar">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                    <div class="calendar-weekday">{{ $dayName }}</div>
                @endforeach
                @foreach ($calendarDays as $day)
                    @php
                        $dateKey = $day->format('Y-m-d');
                        $dayEvents = $eventsByDate[$dateKey] ?? collect();
                        $hasEvents = $dayEvents->isNotEmpty();
                        $isPastEvent = $hasEvents && $day->lt(now()->startOfDay());
                    @endphp
                    <div class="calendar-day {{ $day->month !== $calendarMonth->month ? 'other-month' : '' }} {{ $hasEvents ? 'has-event' : '' }} {{ $isPastEvent ? 'past-event' : '' }}">
                        <span>{{ $day->day }}</span>
                        @if ($hasEvents)
                            <div class="calendar-event-dots">
                                @foreach ($dayEvents->take(3) as $sharing)
                                    <i class="{{ $sharing->txtCalendarSharingIcon ?: 'fa-solid fa-circle' }}" title="{{ $sharing->txtCalendarSharingTheme }}"></i>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection

@if ($isFormOpen)
    @push('modals')
        <x-crud-modal
            id="calendarSharingFormModal"
            :active="$isFormOpen"
            :title="isset($editingCalendarSharing) ? 'Edit Sharing Activity' : 'Add Sharing Activity'"
            subtitle="Isi jadwal, tema, target audiens, dan status kegiatan sharing."
            :close-url="route('calendar-sharing.index', ['month' => $calendarMonth->format('Y-m')])"
            size="lg"
        >
            <form id="calendarSharingForm" class="row g-3" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingCalendarSharing)
                    @method('PUT')
                @endisset

                <div class="col-md-6"><label class="form-label">Theme</label><input class="form-control" name="txtCalendarSharingTheme" value="{{ old('txtCalendarSharingTheme', $editingCalendarSharing->txtCalendarSharingTheme ?? '') }}" required></div>
                <div class="col-md-3"><label class="form-label">Date</label><input class="form-control" type="date" name="dtmCalendarSharingDate" value="{{ old('dtmCalendarSharingDate', isset($editingCalendarSharing) && $editingCalendarSharing->dtmCalendarSharingDate ? $editingCalendarSharing->dtmCalendarSharingDate->format('Y-m-d') : now()->format('Y-m-d')) }}" required></div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="txtCalendarSharingStatus">
                        @foreach (['Open', 'Complete', 'Cancel', 'Reschedule'] as $status)
                            <option value="{{ $status }}" @selected(old('txtCalendarSharingStatus', $editingCalendarSharing->txtCalendarSharingStatus ?? 'Open') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Objective</label><input class="form-control" name="txtCalendarSharingObjective" value="{{ old('txtCalendarSharingObjective', $editingCalendarSharing->txtCalendarSharingObjective ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label">Target Audience</label><input class="form-control" name="txtCalendarSharingTargetAudience" value="{{ old('txtCalendarSharingTargetAudience', $editingCalendarSharing->txtCalendarSharingTargetAudience ?? '') }}"></div>
                <div class="col-12">
                    <label class="form-label">Activity Icon</label>
                    <div class="icon-choice-grid">
                        @foreach ($sharingIcons as $icon)
                            <label class="icon-choice {{ $selectedIcon === $icon['value'] ? 'selected' : '' }}" title="{{ $icon['label'] }}">
                                <input type="radio" name="txtCalendarSharingIcon" value="{{ $icon['value'] }}" @checked($selectedIcon === $icon['value'])>
                                <i class="{{ $icon['value'] }}"></i>
                                <span>{{ $icon['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="txtCalendarSharingDescription" rows="3">{{ old('txtCalendarSharingDescription', $editingCalendarSharing->txtCalendarSharingDescription ?? '') }}</textarea></div>
            </form>

            <x-slot:footer>
                <a href="{{ route('calendar-sharing.index', ['month' => $calendarMonth->format('Y-m')]) }}" class="btn-cancel" style="text-decoration:none;">Cancel</a>
                <button class="btn-save" type="submit" form="calendarSharingForm">{{ isset($editingCalendarSharing) ? 'Save Changes' : 'Save Sharing' }}</button>
            </x-slot:footer>
        </x-crud-modal>
    @endpush
@endif
