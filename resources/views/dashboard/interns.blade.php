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
    $isMentor = optional(\App\Models\MUser::find(session('auth_user_id')))->txtRole === 'Mentor';
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Intern Data</h2>
            <p>Manage profile, university, and internship status data.</p>
        </div>
        @if ($isMentor)
            <a class="btn btn-primary btn-add" href="{{ route('interns.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add Intern</a>
        @endif
    </div>

    @if ($isFormOpen)
        <section class="profile-card mb-4">
            <div class="profile-card-header d-flex align-items-start justify-content-between gap-3">
                <div class="profile-card-title">
                    <h2>{{ isset($editingIntern) ? 'Edit Intern' : 'Add New Intern' }}</h2>
                    <p>Email and password are stored in mUser. Intern profiles are stored in mIntern.</p>
                </div>
                <a href="{{ route('interns.index') }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-xmark"></i> Close</a>
            </div>

            <form class="row g-3" action="{{ $formAction }}" method="POST">
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
                    <label class="form-label">Major</label>
                    <input class="form-control" name="txtMajor" value="{{ old('txtMajor', $editingIntern->txtMajor ?? '') }}">
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
                    <button class="btn btn-primary btn-save" type="submit">{{ isset($editingIntern) ? 'Save Changes' : 'Save Intern' }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr><th>ID</th><th>Full Name</th><th>Gender</th><th>Email</th><th>University</th><th>Major</th><th>Join Date</th><th>End Date</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($interns as $intern)
                        <tr>
                            <td>{{ $intern->txtInternNo ?: 'INT-' . str_pad((string) $intern->intIntern_ID, 3, '0', STR_PAD_LEFT) }}</td>
                            <td><a class="auth-link" href="{{ route('profile.intern.show', $intern->intIntern_ID) }}">{{ $intern->txtInternName }}</a></td>
                            <td>{{ $intern->txtInternGender ?: '-' }}</td>
                            <td>{{ $intern->user->txtEmail ?? '-' }}</td>
                            <td>{{ $intern->txtUniversity ?: '-' }}</td>
                            <td>{{ $intern->txtMajor ?: '-' }}</td>
                            <td>{{ $intern->dtmInserted?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $intern->dtmEndDate?->format('d M Y') ?? '-' }}</td>
                            <td><span class="status-badge {{ $intern->bitActive ? 'status-active' : 'status-inactive' }}">{{ $intern->bitActive ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                @if ($isMentor)
                                    <div class="action-btns">
                                        <a class="btn-icon btn-edit" href="{{ route('interns.edit', $intern->intIntern_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                        <form action="{{ route('interns.destroy', $intern->intIntern_ID) }}" method="POST" onsubmit="return confirm('Deactivate this intern?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn-icon btn-delete" type="submit"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                @else
                                    <a class="auth-link" href="{{ route('profile.intern.show', $intern->intIntern_ID) }}">View</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="center">No intern data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
