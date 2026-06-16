@extends('layouts.app', [
    'title' => 'Projects - Kalbe Internship Dashboard',
    'pageTitle' => 'PROJECTS',
    'pageSubtitle' => 'Manajemen data Projects Kalbe.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingProject)
        ? route('projects.update', $editingProject->intProject_ID)
        : route('projects.store');
    $assignment = isset($editingProject) ? $editingProject->assignments->first() : null;
@endphp

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Data Projects</h2>
            <p>Pantau status dan PIC untuk Collaboration, Main, Satellite, dan Sharing.</p>
        </div>
        <a class="btn-add" href="{{ route('projects.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Tambah Project</a>
    </div>

    @if ($isFormOpen)
        <section class="profile-card" style="margin-bottom: 20px;">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>{{ isset($editingProject) ? 'Edit Project' : 'Tambah Project Baru' }}</h2>
                    <p>Master project disimpan di mProject. Assignment intern, mentor, dan progress disimpan di trInternProject.</p>
                </div>
                <a href="{{ route('projects.index') }}" class="btn-outline"><i class="fa-solid fa-xmark"></i> Tutup</a>
            </div>

            <form class="edit-profile-grid" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingProject)
                    @method('PUT')
                @endisset

                <div class="form-group">
                    <label>Nama Project</label>
                    <input class="form-control" name="txtProjectName" value="{{ old('txtProjectName', $editingProject->txtProjectName ?? '') }}" required>
                </div>
                <div class="form-group">
                    <label>Tipe</label>
                    <select class="form-control" name="txtProjectType" required>
                        @foreach (['Main', 'Satellite', 'Collaboration', 'Sharing'] as $type)
                            <option value="{{ $type }}" @selected(old('txtProjectType', $editingProject->txtProjectType ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Status Project</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingProject->bitActive ?? true)) === '1')>Aktif</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingProject->bitActive ?? true)) === '0')>Nonaktif</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status Assignment</label>
                    <input class="form-control" name="txtStatus" value="{{ old('txtStatus', $assignment->txtStatus ?? 'On Track') }}">
                </div>
                <div class="form-group">
                    <label>Intern</label>
                    <select class="form-control" name="intIntern_ID">
                        <option value="">Belum ditentukan</option>
                        @foreach ($interns as $intern)
                            <option value="{{ $intern->intIntern_ID }}" @selected((string) old('intIntern_ID', $assignment->intIntern_ID ?? '') === (string) $intern->intIntern_ID)>{{ $intern->txtInternName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Mentor</label>
                    <select class="form-control" name="intMentor_ID">
                        <option value="">Belum ditentukan</option>
                        @foreach ($mentors as $mentor)
                            <option value="{{ $mentor->intMentor_ID }}" @selected((string) old('intMentor_ID', $assignment->intMentor_ID ?? '') === (string) $mentor->intMentor_ID)>{{ $mentor->txtMentorName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Progress (%)</label>
                    <input class="form-control" type="number" min="0" max="100" name="floatProgress" value="{{ old('floatProgress', $assignment->floatProgress ?? 0) }}">
                </div>
                <div class="form-group full">
                    <label>Deskripsi</label>
                    <textarea class="form-control" name="txtDescription" rows="3">{{ old('txtDescription', $editingProject->txtDescription ?? '') }}</textarea>
                </div>
                <div class="form-group full">
                    <button class="btn-save" type="submit">{{ isset($editingProject) ? 'Simpan Perubahan' : 'Simpan Project' }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Nama Project</th><th>Tipe</th><th>Intern</th><th>PIC / Mentor</th><th>Progress</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                        @php($rowAssignment = $project->assignments->first())
                        <tr>
                            <td><strong>{{ $project->txtProjectName }}</strong><br><span style="color:var(--text-gray); font-size:11px;">{{ $project->txtDescription }}</span></td>
                            <td>{{ $project->txtProjectType }}</td>
                            <td>{{ $rowAssignment?->intern?->txtInternName ?? '-' }}</td>
                            <td>{{ $rowAssignment?->mentor?->txtMentorName ?? '-' }}</td>
                            <td>
                                @php($progress = (float) ($rowAssignment?->floatProgress ?? 0))
                                <div style="display:flex; align-items:center; gap:8px; min-width:120px;">
                                    <div style="background:var(--border); border-radius:3px; height:8px; flex:1;"><div style="background:var(--secondary); width:{{ $progress }}%; height:100%; border-radius:3px;"></div></div>
                                    <strong>{{ number_format($progress, 0) }}%</strong>
                                </div>
                            </td>
                            <td><span class="status-badge {{ $project->bitActive ? 'status-active' : 'status-inactive' }}">{{ $project->bitActive ? ($rowAssignment?->txtStatus ?? 'Aktif') : 'Nonaktif' }}</span></td>
                            <td>
                                <div class="action-btns">
                                    <a class="btn-icon btn-edit" href="{{ route('projects.edit', $project->intProject_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                    <form action="{{ route('projects.destroy', $project->intProject_ID) }}" method="POST" onsubmit="return confirm('Nonaktifkan project ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-icon btn-delete" type="submit"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="center">Belum ada data project.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
