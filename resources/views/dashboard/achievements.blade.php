@extends('layouts.app', [
    'title' => 'Achievements - Kalbe Internship Dashboard',
    'pageTitle' => 'ACHIEVEMENTS',
    'pageSubtitle' => 'Manajemen data Achievements Kalbe.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingAchievement)
        ? route('achievements.update', $editingAchievement->intAchievement_ID)
        : route('achievements.store');
    $isMentor = optional(\App\Models\MUser::find(session('auth_user_id')))->txtRole === 'Mentor';
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
    <div class="page-crud-header">
        <div>
            <h2>Achievements</h2>
            <p>Penghargaan dan milestone untuk intern.</p>
        </div>
        @if ($isMentor)
            <a class="btn-add" href="{{ route('achievements.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Tambah Achievement</a>
        @endif
    </div>

    @if ($isFormOpen)
        <section class="profile-card" style="margin-bottom: 20px;">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>{{ isset($editingAchievement) ? 'Edit Achievement' : 'Tambah Achievement' }}</h2>
                    <p>Data achievement disimpan di trAchievement dan terhubung ke intern.</p>
                </div>
                <a href="{{ route('achievements.index') }}" class="btn-outline"><i class="fa-solid fa-xmark"></i> Tutup</a>
            </div>

            <form class="edit-profile-grid" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingAchievement)
                    @method('PUT')
                @endisset

                <div class="form-group">
                    <label>Intern</label>
                    <select class="form-control" name="intIntern_ID" required>
                        <option value="">Pilih intern</option>
                        @foreach ($interns as $intern)
                            <option value="{{ $intern->intIntern_ID }}" @selected((string) old('intIntern_ID', $editingAchievement->intIntern_ID ?? '') === (string) $intern->intIntern_ID)>{{ $intern->txtInternName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingAchievement->bitActive ?? true)) === '1')>Aktif</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingAchievement->bitActive ?? true)) === '0')>Nonaktif</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Judul</label>
                    <input class="form-control" name="txtAchievementTitle" value="{{ old('txtAchievementTitle', $editingAchievement->txtAchievementTitle ?? '') }}" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Award</label>
                    <input class="form-control" type="date" name="dtmAwarded" value="{{ old('dtmAwarded', isset($editingAchievement) && $editingAchievement->dtmAwarded ? $editingAchievement->dtmAwarded->format('Y-m-d') : now()->format('Y-m-d')) }}">
                </div>
                <div class="form-group full">
                    <label>Icon Achievement</label>
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
                <div class="form-group full">
                    <label>Deskripsi</label>
                    <textarea class="form-control" name="txtDescription" rows="3">{{ old('txtDescription', $editingAchievement->txtDescription ?? '') }}</textarea>
                </div>
                <div class="form-group full">
                    <button class="btn-save" type="submit">{{ isset($editingAchievement) ? 'Simpan Perubahan' : 'Simpan Achievement' }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="profile-section-grid">
        <section class="profile-card">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>Achievement List</h2>
                    <p>Catatan pencapaian program internship.</p>
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
                        @if ($isMentor)
                            <div class="action-btns">
                                <a class="btn-icon btn-edit" href="{{ route('achievements.edit', $achievement->intAchievement_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                <form action="{{ route('achievements.destroy', $achievement->intAchievement_ID) }}" method="POST" onsubmit="return confirm('Nonaktifkan achievement ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-icon btn-delete" type="submit"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        @endif
                    </li>
                @empty
                    <li><div class="achievement-icon"><i class="fa-solid fa-award"></i></div><div><h4>Belum ada data</h4><p>Tambahkan achievement pertama untuk intern.</p></div></li>
                @endforelse
            </ul>
        </section>
        <aside class="profile-card">
            <div class="profile-card-title">
                <h2>Summary</h2>
                <p>Total award aktif bulan ini.</p>
            </div>
            <div class="kpi-card" style="margin-top: 18px;">
                <div class="kpi-icon"><i class="fa-solid fa-trophy"></i></div>
                <div class="kpi-data"><h4>Total Awards</h4><h2>{{ $achievements->where('bitActive', true)->count() }}</h2><p>Across all interns</p></div>
            </div>
        </aside>
    </div>
@endsection
