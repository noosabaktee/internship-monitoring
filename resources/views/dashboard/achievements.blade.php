@extends('layouts.app', [
    'title' => 'Achievements - Kalbe Internship Dashboard',
    'pageTitle' => 'ACHIEVEMENTS',
    'pageSubtitle' => 'Manage Kalbe achievement data.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingAchievement)
        ? route('achievements.update', $editingAchievement->intAchievement_ID)
        : route('achievements.store');
    $authUser = \App\Models\MUser::with('intern')->find(session('auth_user_id'));
    $canManageAchievements = $authUser && \App\Support\RoleAccess::can($authUser, 'crud-achievements');
    $achievementIcons = [
        ['value' => 'fa-solid fa-trophy', 'label' => 'Trophy'],
        ['value' => 'fa-solid fa-award', 'label' => 'Award'],
        ['value' => 'fa-solid fa-medal', 'label' => 'Medal'],
        ['value' => 'fa-solid fa-star', 'label' => 'Star'],
        ['value' => 'fa-solid fa-crown', 'label' => 'Crown'],
        ['value' => 'fa-solid fa-lightbulb', 'label' => 'Idea'],
        ['value' => 'fa-solid fa-rocket', 'label' => 'Rocket'],
        ['value' => 'fa-solid fa-fire', 'label' => 'Fire'],
        ['value' => 'fa-solid fa-bolt', 'label' => 'Impact'],
        ['value' => 'fa-solid fa-chart-line', 'label' => 'Growth'],
        ['value' => 'fa-solid fa-arrow-trend-up', 'label' => 'Progress'],
        ['value' => 'fa-solid fa-handshake', 'label' => 'Collaboration'],
        ['value' => 'fa-solid fa-users', 'label' => 'Team'],
        ['value' => 'fa-solid fa-user-graduate', 'label' => 'Learning'],
        ['value' => 'fa-solid fa-book-open', 'label' => 'Sharing'],
        ['value' => 'fa-solid fa-brain', 'label' => 'Skill'],
        ['value' => 'fa-solid fa-gears', 'label' => 'Execution'],
        ['value' => 'fa-solid fa-check-double', 'label' => 'Completed'],
        ['value' => 'fa-solid fa-bullseye', 'label' => 'Target'],
        ['value' => 'fa-solid fa-gem', 'label' => 'Excellence'],
    ];
    $selectedIcon = old('txtIcon', $editingAchievement->txtIcon ?? 'fa-solid fa-award');
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Achievements</h2>
            <p>Awards and milestones for interns.</p>
        </div>
        @if ($canManageAchievements)
            <a class="btn btn-primary btn-add" href="{{ route('achievements.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add Achievement</a>
        @endif
    </div>

    <div class="profile-section-grid">
        <section class="profile-card">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>Achievement List</h2>
                    <p>Internship program achievement records.</p>
                </div>
            </div>
            <ul class="achievement-list">
                @forelse ($achievements as $achievement)
                    <li>
                        <div class="achievement-icon"><i class="{{ $achievement->txtIcon ?: 'fa-solid fa-award' }}"></i></div>
                        <div style="flex:1;">
                            <h4>{{ $achievement->txtAchievementTitle }}</h4>
                            <p>{{ $achievement->intern->txtInternName ?? '-' }} &bull; {{ $achievement->dtmAwarded?->format('d M Y') ?? '-' }}</p>
                            <p>{{ $achievement->txtDescription }}</p>
                        </div>
                        @if ($canManageAchievements)
                            <div class="action-btns">
                                <a class="btn-icon btn-edit" href="{{ route('achievements.edit', $achievement->intAchievement_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                <button
                                    class="btn-icon btn-delete"
                                    type="button"
                                    data-delete-modal-trigger
                                    data-delete-action="{{ route('achievements.destroy', $achievement->intAchievement_ID) }}"
                                    data-delete-title="Deactivate Achievement?"
                                    data-delete-message="{{ $achievement->txtAchievementTitle }} will be marked inactive."
                                    data-delete-submit="Deactivate"
                                ><i class="fa-solid fa-trash"></i></button>
                            </div>
                        @endif
                    </li>
                @empty
                    <li><div class="achievement-icon"><i class="fa-solid fa-award"></i></div><div><h4>No data yet</h4><p>Add the first achievement for an intern.</p></div></li>
                @endforelse
            </ul>
        </section>
        <aside class="profile-card">
            <div class="profile-card-title">
                <h2>Summary</h2>
                <p>Total active awards this month.</p>
            </div>
            <div class="kpi-card" style="margin-top: 18px;">
                <div class="kpi-icon"><i class="fa-solid fa-trophy"></i></div>
                <div class="kpi-data"><h4>Total Awards</h4><h2>{{ $achievements->where('bitActive', true)->count() }}</h2><p>Across all interns</p></div>
            </div>
        </aside>
    </div>
@endsection

@if ($isFormOpen)
    @push('modals')
        <x-crud-modal
            id="achievementFormModal"
            :active="$isFormOpen"
            :title="isset($editingAchievement) ? 'Edit Achievement' : 'Add Achievement'"
            subtitle="Isi penghargaan atau milestone yang diterima intern."
            :close-url="route('achievements.index')"
            size="lg"
        >
            <form id="achievementForm" class="row g-3" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingAchievement)
                    @method('PUT')
                @endisset

                <div class="col-md-6">
                    <label class="form-label">Intern</label>
                    <select class="form-control" name="intIntern_ID" required>
                        <option value="">Select intern</option>
                        @foreach ($interns as $intern)
                            <option value="{{ $intern->intIntern_ID }}" @selected((string) old('intIntern_ID', $editingAchievement->intIntern_ID ?? '') === (string) $intern->intIntern_ID)>{{ $intern->txtInternName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingAchievement->bitActive ?? true)) === '1')>Active</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingAchievement->bitActive ?? true)) === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Title</label>
                    <input class="form-control" name="txtAchievementTitle" value="{{ old('txtAchievementTitle', $editingAchievement->txtAchievementTitle ?? '') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Award Date</label>
                    <input class="form-control" type="date" name="dtmAwarded" value="{{ old('dtmAwarded', isset($editingAchievement) && $editingAchievement->dtmAwarded ? $editingAchievement->dtmAwarded->format('Y-m-d') : now()->format('Y-m-d')) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Achievement Icon</label>
                    <div class="icon-choice-grid">
                        @foreach ($achievementIcons as $icon)
                            <label class="icon-choice {{ $selectedIcon === $icon['value'] ? 'selected' : '' }}" title="{{ $icon['label'] }}">
                                <input type="radio" name="txtIcon" value="{{ $icon['value'] }}" @checked($selectedIcon === $icon['value'])>
                                <i class="{{ $icon['value'] }}"></i>
                                <span>{{ $icon['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="txtDescription" rows="3">{{ old('txtDescription', $editingAchievement->txtDescription ?? '') }}</textarea>
                </div>
            </form>

            <x-slot:footer>
                <a href="{{ route('achievements.index') }}" class="btn-cancel" style="text-decoration:none;">Cancel</a>
                <button class="btn-save" type="submit" form="achievementForm">{{ isset($editingAchievement) ? 'Save Changes' : 'Save Achievement' }}</button>
            </x-slot:footer>
        </x-crud-modal>
    @endpush
@endif
