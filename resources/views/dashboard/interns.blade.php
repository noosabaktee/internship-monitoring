@extends('layouts.app', [
    'title' => 'Interns - Kalbe Internship Dashboard',
    'pageTitle' => 'INTERNS',
    'pageSubtitle' => 'Manage Kalbe intern data.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingIntern)
        ? route('interns.update', $editingIntern->intIntern_ID)
        : route('interns.store');
    $authUser = \App\Models\MUser::with('intern')->find(session('auth_user_id'));
    $canManageInterns = $authUser && \App\Support\RoleAccess::can($authUser, 'master-data');
    $internTypeOptions = [
        'digitalisasi' => 'Digitalisasi',
        'regular' => 'Regular',
        'pkl' => 'PKL',
    ];
    $extendDates = old('txtInternExtendEndDates', $editingIntern->txtInternExtendEndDates ?? []);
    $extendDates = is_array($extendDates) ? array_values(array_filter($extendDates)) : [];
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Intern Data</h2>
            <p>Manage profile, university, and internship status data.</p>
        </div>
        @if ($canManageInterns)
            <a class="btn btn-primary btn-add" href="{{ route('interns.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add Intern</a>
        @endif
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr><th>ID</th><th>Full Name</th><th>Type</th><th>Salary / Day</th><th>Gender</th><th>Email</th><th>University</th><th>Dept</th><th>Join Date</th><th>End Date</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($interns as $intern)
                        @php
                            $internName = $intern->txtInternName ?: 'Intern';
                            $internPhotoUrl = $intern->user?->txtProfilePhoto
                                ? asset('storage/' . $intern->user->txtProfilePhoto)
                                : 'https://ui-avatars.com/api/?name=' . urlencode($internName) . '&background=8CC63F&color=fff&bold=true';
                        @endphp
                        <tr>
                            <td>{{ $intern->txtInternNo ?: 'INT-' . str_pad((string) $intern->intIntern_ID, 3, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                <a class="table-person table-person-link" href="{{ route('profile.intern.show', $intern->intIntern_ID) }}">
                                    <img class="table-person-avatar" src="{{ $internPhotoUrl }}" alt="{{ $internName }}" loading="lazy">
                                    <span class="table-person-name">{{ $intern->txtInternName ?: '-' }}</span>
                                </a>
                            </td>
                            <td>{{ $internTypeOptions[$intern->txtInternType ?: 'digitalisasi'] ?? ucfirst((string) $intern->txtInternType) }}</td>
                            <td>Rp {{ number_format((float) ($intern->floatInternSalary ?? 0), 0, ',', '.') }}</td>
                            <td>{{ $intern->txtInternGender ?: '-' }}</td>
                            <td>{{ $intern->user->txtEmail ?? '-' }}</td>
                            <td>{{ $intern->txtUniversity ?: '-' }}</td>
                            <td>{{ $intern->txtDept ?: '-' }}</td>
                            <td>{{ $intern->dtmInserted?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $intern->dtmEndDate?->format('d M Y') ?? '-' }}</td>
                            <td><span class="status-badge {{ $intern->bitActive ? 'status-active' : 'status-inactive' }}">{{ $intern->bitActive ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                @if ($canManageInterns)
                                    <div class="action-btns">
                                        <a class="btn-icon btn-edit" href="{{ route('interns.edit', $intern->intIntern_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                        <button
                                            class="btn-icon btn-delete"
                                            type="button"
                                            data-delete-modal-trigger
                                            data-delete-action="{{ route('interns.destroy', $intern->intIntern_ID) }}"
                                            data-delete-title="Deactivate Intern?"
                                            data-delete-message="{{ $intern->txtInternName ?: 'This intern' }} will be marked inactive along with the linked user account."
                                            data-delete-submit="Deactivate"
                                        ><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @else
                                    <a class="auth-link" href="{{ route('profile.intern.show', $intern->intIntern_ID) }}">View</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="center">No intern data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@if ($isFormOpen)
    @push('modals')
        <x-crud-modal
            id="internFormModal"
            :active="$isFormOpen"
            :title="isset($editingIntern) ? 'Edit Intern' : 'Add New Intern'"
            subtitle="Isi data intern, akun login, dan periode magang."
            :close-url="route('interns.index')"
            size="lg"
        >
            <form id="internForm" class="row g-3" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingIntern)
                    @method('PUT')
                @endisset

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingIntern->bitActive ?? true)) === '1')>Active</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingIntern->bitActive ?? true)) === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Full Name</label>
                    <input class="form-control" name="txtInternName" value="{{ old('txtInternName', $editingIntern->txtInternName ?? '') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select class="form-control" name="txtInternGender">
                        <option value="">Select gender</option>
                        @foreach (['Laki-laki' => 'Male', 'Perempuan' => 'Female'] as $genderValue => $genderLabel)
                            <option value="{{ $genderValue }}" @selected(old('txtInternGender', $editingIntern->txtInternGender ?? '') === $genderValue)>{{ $genderLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="txtEmail" value="{{ old('txtEmail', $editingIntern->user->txtEmail ?? '') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password {{ isset($editingIntern) ? '(leave blank to keep current)' : '' }}</label>
                    <input class="form-control" type="password" name="txtPassword" placeholder="{{ isset($editingIntern) ? 'New password' : 'Default: password' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">University</label>
                    <input class="form-control" name="txtUniversity" value="{{ old('txtUniversity', $editingIntern->txtUniversity ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Dept</label>
                    <input class="form-control" name="txtDept" value="{{ old('txtDept', $editingIntern->txtDept ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Intern Type</label>
                    <select class="form-control" name="txtInternType" required>
                        @foreach ($internTypeOptions as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}" @selected(old('txtInternType', $editingIntern->txtInternType ?? 'digitalisasi') === $typeValue)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Salary / Workday</label>
                    <input class="form-control" type="number" min="0" step="1000" name="floatInternSalary" value="{{ old('floatInternSalary', isset($editingIntern) ? number_format((float) ($editingIntern->floatInternSalary ?? 0), 0, '.', '') : 0) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Join Date</label>
                    <input class="form-control" type="date" name="dtmInserted" value="{{ old('dtmInserted', isset($editingIntern) && $editingIntern->dtmInserted ? $editingIntern->dtmInserted->format('Y-m-d') : now()->format('Y-m-d')) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Date</label>
                    <input class="form-control" type="date" name="dtmEndDate" value="{{ old('dtmEndDate', isset($editingIntern) && $editingIntern->dtmEndDate ? $editingIntern->dtmEndDate->format('Y-m-d') : '') }}">
                </div>
                <div class="col-12">
                    <div class="intern-extend-grid" id="internExtendFields">
                        @foreach ($extendDates as $extendIndex => $extendDate)
                            <div class="intern-extend-field">
                                <label class="form-label">Extend {{ $extendIndex + 1 }} End Date</label>
                                <input class="form-control intern-extend-date" type="date" name="txtInternExtendEndDates[]" value="{{ $extendDate }}">
                            </div>
                        @endforeach
                    </div>
                    <button class="btn btn-outline-primary btn-sm intern-extend-button" id="internExtendButton" type="button" data-base-input="dtmEndDate" data-target="internExtendFields">
                        <i class="fa-solid fa-calendar-plus"></i> Extend
                    </button>
                    <p class="intern-extend-note" id="internExtendNote">Extend can be added when the latest end date is within 14 days.</p>
                </div>
            </form>

            <x-slot:footer>
                <a href="{{ route('interns.index') }}" class="btn-cancel" style="text-decoration:none;">Cancel</a>
                <button class="btn-save" type="submit" form="internForm">{{ isset($editingIntern) ? 'Save Changes' : 'Save Intern' }}</button>
            </x-slot:footer>
        </x-crud-modal>
    @endpush
@endif
