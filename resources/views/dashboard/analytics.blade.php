@extends('layouts.app', [
    'title' => 'Rapor Intern - Kalbe Internship Dashboard',
    'pageTitle' => 'RAPOR INTERN',
    'pageSubtitle' => 'Evaluasi final satu kali dan penerbitan sertifikat internship.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingEvaluation)
        ? route('analytics.update', $editingEvaluation->intEvaluation_ID)
        : route('analytics.store');
    $formInterns = isset($editingEvaluation) ? collect([$editingEvaluation->intern]) : $interns;
    $isInternViewer = \App\Support\RoleAccess::isIntern($authUser);
@endphp

@section('content')
    <section class="report-hero">
        <div>
            <span class="report-kicker"><i class="fa-solid fa-graduation-cap"></i> Final Internship Review</span>
            <h2>Rapor akhir yang bermakna, sertifikat yang siap dibawa pulang.</h2>
            <p>Setiap intern hanya memiliki satu rapor final. Sertifikat baru dapat diunduh intern setelah diterbitkan.</p>
        </div>
        @if ($canManageAnalytics && $interns->isNotEmpty())
            <a class="btn btn-primary btn-add" href="{{ route('analytics.create') }}"><i class="fa-solid fa-certificate"></i> Add Sertifikat</a>
        @endif
    </section>

    @if (!\App\Support\RoleAccess::isIntern($authUser))
        <div class="kpi-row">
            <div class="kpi-card"><div class="kpi-icon"><i class="fa-solid fa-file-circle-check"></i></div><div class="kpi-data"><h4>Rapor Terbit</h4><h2>{{ $evaluatedInterns }}</h2><p>Satu rapor per intern</p></div></div>
            <div class="kpi-card"><div class="kpi-icon"><i class="fa-solid fa-chart-line"></i></div><div class="kpi-data"><h4>Nilai Rata-rata</h4><h2>{{ number_format($averageExposure, 1) }}</h2><p>Skala 0–100</p></div></div>
            <div class="kpi-card"><div class="kpi-icon"><i class="fa-solid fa-certificate"></i></div><div class="kpi-data"><h4>Sertifikat Terbit</h4><h2>{{ $publishedCertificates }}</h2><p>{{ $evaluations->count() - $publishedCertificates }} menunggu diterbitkan</p></div></div>
        </div>
    @endif

    <section class="card report-list-card">
        <div class="attendance-panel-head">
            <div>
                <h3>{{ \App\Support\RoleAccess::isIntern($authUser) ? 'Rapor Final Saya' : 'Rapor Final Intern' }}</h3>
                <p>{{ $isInternViewer ? 'Nilai dan catatan akan tampil setelah sertifikat diterbitkan.' : 'Nilai final, catatan pengembangan, dan akses sertifikat.' }}</p>
            </div>
            @if ($canManageAnalytics && $interns->isEmpty())
                <span class="attendance-map-chip"><i class="fa-solid fa-circle-check"></i> Semua intern sudah dibuatkan sertifikat</span>
            @endif
        </div>

        <div class="report-card-grid">
            @forelse ($evaluations as $evaluation)
                @php
                    $score = (float) $evaluation->floatExposureScore;
                    $grade = \App\Models\TrEvaluation::gradeFor($score);
                    $evaluatorName = $evaluation->evaluator?->mentor?->txtMentorName
                        ?? $evaluation->evaluator?->adminProfile?->txtAdminProfileName
                        ?? $evaluation->evaluator?->txtEmail
                        ?? 'Kalbe Digital Core';
                @endphp
                <article class="report-card">
                    @if ($isInternViewer && ! $evaluation->bitEvaluationCertificatePublished)
                        <div class="report-card-top">
                            <div class="report-avatar">{{ mb_strtoupper(mb_substr($evaluation->intern?->txtInternName ?? '?', 0, 1)) }}</div>
                            <div>
                                <h3>{{ $evaluation->intern?->txtInternName ?? '-' }}</h3>
                                <p>{{ $evaluation->intern?->txtDept ?: 'Internship Program' }} · {{ $evaluation->intern?->txtInternNo }}</p>
                            </div>
                            <div class="report-pending-lock" aria-hidden="true"><i class="fa-solid fa-lock"></i></div>
                        </div>

                        <div class="report-pending-message">
                            <span><i class="fa-solid fa-hourglass-half"></i></span>
                            <div>
                                <h4>Rapor masih menunggu penerbitan</h4>
                                <p>Mentor atau Headmaster belum menerbitkan sertifikat. Nilai dan catatan evaluasi akan ditampilkan setelah sertifikat resmi diterbitkan.</p>
                            </div>
                        </div>

                        <div class="report-card-footer report-card-footer-waiting">
                            <small><i class="fa-solid fa-shield-halved"></i> Hasil evaluasi masih bersifat internal.</small>
                            <span class="certificate-waiting-chip"><i class="fa-solid fa-clock"></i> Menunggu diterbitkan</span>
                        </div>
                    @else
                    <div class="report-card-top">
                        <div class="report-avatar">{{ mb_strtoupper(mb_substr($evaluation->intern?->txtInternName ?? '?', 0, 1)) }}</div>
                        <div>
                            <h3>{{ $evaluation->intern?->txtInternName ?? '-' }}</h3>
                            <p>{{ $evaluation->intern?->txtDept ?: 'Internship Program' }} · {{ $evaluation->intern?->txtInternNo }}</p>
                        </div>
                        <div class="report-score-ring"><strong>{{ number_format($score, 0) }}</strong><span>{{ $grade }}</span></div>
                    </div>

                    <div class="report-score-grid">
                        @foreach ($evaluation->assessmentCriteria() as $criterion)
                            <div>
                                <span>{{ $criterion['label'] }}</span>
                                <strong>{{ number_format($criterion['score'], 0) }} · {{ $criterion['grade'] }}</strong>
                            </div>
                        @endforeach
                    </div>

                    <div class="report-card-footer">
                        <small>
                            <i class="fa-regular fa-calendar-check"></i> Final {{ $evaluation->dtmEvaluationCompleted?->format('d M Y') }} · {{ $evaluatorName }}
                            <span class="certificate-publish-status {{ $evaluation->bitEvaluationCertificatePublished ? 'is-published' : 'is-draft' }}">
                                <i class="fa-solid {{ $evaluation->bitEvaluationCertificatePublished ? 'fa-circle-check' : 'fa-clock' }}"></i>
                                {{ $evaluation->bitEvaluationCertificatePublished ? 'Sertifikat terbit' : 'Sertifikat belum terbit' }}
                            </span>
                        </small>
                        <div class="action-btns">
                            @if ($canManageAnalytics)
                                <a class="btn-icon btn-edit" href="{{ route('analytics.edit', $evaluation->intEvaluation_ID) }}" title="Edit rapor"><i class="fa-solid fa-pen"></i></a>
                            @endif
                            @if ($evaluation->bitEvaluationCertificatePublished)
                                <a class="btn btn-primary btn-sm" href="{{ route('analytics.certificate', $evaluation->intEvaluation_ID) }}"><i class="fa-solid fa-download"></i> Unduh Sertifikat</a>
                            @elseif ($canManageAnalytics)
                                <form action="{{ route('analytics.certificate.publish', $evaluation->intEvaluation_ID) }}" method="POST" data-confirm="Terbitkan sertifikat ini? Setelah diterbitkan, intern dapat langsung mengunduhnya.">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-paper-plane"></i> Terbitkan Sertifikat</button>
                                </form>
                            @else
                                <span class="certificate-waiting-chip"><i class="fa-solid fa-lock"></i> Menunggu diterbitkan</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </article>
            @empty
                <div class="report-empty-state">
                    <span><i class="fa-solid fa-award"></i></span>
                    <h3>Rapor final belum tersedia</h3>
                    <p>{{ \App\Support\RoleAccess::isIntern($authUser) ? 'Mentor akan mengisi rapor saat sertifikat kamu disiapkan.' : 'Klik Add Sertifikat untuk memilih intern yang belum dibuatkan sertifikat.' }}</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection

@if ($isFormOpen)
    @push('modals')
        <x-crud-modal
            id="evaluationFormModal"
            :active="true"
            :title="isset($editingEvaluation) ? 'Perbarui Sertifikat' : 'Add Sertifikat'"
            subtitle="Pilih intern yang belum dibuatkan sertifikat. Setiap intern hanya memiliki satu rapor final sebagai dasar penerbitan sertifikat."
            :close-url="route('analytics.index')"
            size="lg"
        >
            <form id="evaluationForm" class="form-grid form-grid-2" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingEvaluation) @method('PUT') @endisset

                <div class="form-group form-span-full">
                    <label class="form-label" for="evaluationIntern">Intern <span class="required">*</span></label>
                    <select id="evaluationIntern" class="form-control" name="intIntern_ID" required @disabled(isset($editingEvaluation))>
                        <option value="">Pilih intern</option>
                        @foreach ($formInterns as $intern)
                            <option value="{{ $intern->intIntern_ID }}" @selected((string) old('intIntern_ID', $editingEvaluation->intIntern_ID ?? $selectedInternId ?? '') === (string) $intern->intIntern_ID)>
                                {{ $intern->txtInternName }} · berakhir {{ $intern->effectiveEndDate()?->format('d M Y') ?? 'belum ditentukan' }}
                            </option>
                        @endforeach
                    </select>
                    @isset($editingEvaluation)<input type="hidden" name="intIntern_ID" value="{{ $editingEvaluation->intIntern_ID }}">@endisset
                    <small class="field-help">Dropdown hanya berisi intern aktif yang belum memiliki rapor atau sertifikat.</small>
                </div>

                @foreach ([
                    'floatDisciplineAttendance' => ['Discipline & Attendance', 'fa-calendar-check'],
                    'floatResponsibilityInitiative' => ['Responsibility & Initiative', 'fa-person-circle-check'],
                    'floatTechnicalSkills' => ['Technical Skills', 'fa-code'],
                    'floatTeamwork' => ['Teamwork', 'fa-people-group'],
                    'floatCommunicationSkills' => ['Communication Skills', 'fa-comments'],
                    'floatCreativityProblemSolving' => ['Creativity & Problem-Solving', 'fa-lightbulb'],
                    'floatProfessionalismWorkEthics' => ['Professionalism & Work Ethics', 'fa-user-tie'],
                ] as $field => [$label, $icon])
                    <div class="form-group score-input-group">
                        <label class="form-label" for="{{ $field }}"><i class="fa-solid {{ $icon }}"></i> {{ $label }}</label>
                        <input id="{{ $field }}" class="form-control assessment-score-input" type="number" min="0" max="100" step="0.01" name="{{ $field }}" value="{{ old($field, $editingEvaluation->{$field} ?? '') }}" placeholder="0–100" required>
                        <small class="field-help">Grade otomatis: <strong data-grade-for="{{ $field }}">-</strong></small>
                    </div>
                @endforeach

            </form>

            <x-slot:footer>
                <a href="{{ route('analytics.index') }}" class="btn-cancel">Batal</a>
                <button class="btn-save" type="submit" form="evaluationForm"><i class="fa-solid fa-file-circle-check"></i> {{ isset($editingEvaluation) ? 'Simpan Perubahan' : 'Simpan Sertifikat' }}</button>
            </x-slot:footer>
        </x-crud-modal>
    @endpush

    @push('head')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const gradeFor = (value) => {
                    const score = Number(value);
                    if (!Number.isFinite(score) || value === '') return '-';
                    if (score >= 90) return 'A';
                    if (score >= 80) return 'B';
                    if (score >= 70) return 'C';
                    if (score >= 60) return 'D';
                    return 'E';
                };

                document.querySelectorAll('.assessment-score-input').forEach((input) => {
                    const output = document.querySelector(`[data-grade-for="${input.id}"]`);
                    const update = () => {
                        if (output) output.textContent = gradeFor(input.value);
                    };
                    input.addEventListener('input', update);
                    update();
                });
            });
        </script>
    @endpush
@endif
