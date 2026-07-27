@extends('layouts.app', [
'title' => 'Pengajuan WFH - Kalbe Internship Dashboard',
'pageTitle' => 'PENGAJUAN WFH, SAKIT & IZIN',
'pageSubtitle' => $isAdmin ? 'Review pengajuan WFH, sakit, dan izin intern.' : 'Ajukan WFH, sakit, atau izin pada satu halaman.',
])

@php
$wfhFormHasErrors = collect([
'txtWorkFromHomeRequestType',
'dtmWorkFromHomeRequestStartDate',
'dtmWorkFromHomeRequestEndDate',
'txtWorkFromHomeRequestReason',
'txtWorkFromHomeRequestAttachment',
'wfh',
])->contains(fn ($field) => $errors->has($field));
@endphp

@section('content')
<section class="wfh-hero">
    <div>
        <span class="report-kicker"><i class="fa-solid fa-house-laptop"></i> Pengajuan Intern</span>
        <h2>{{ $isAdmin ? 'Kelola persetujuan WFH, sakit, dan izin dengan cepat.' : 'Satu pengajuan untuk WFH, sakit, dan izin.' }}</h2>
        <p>{{ $isAdmin ? 'WFH tetap membutuhkan absensi setelah disetujui, sedangkan sakit dan izin akan masuk recap tanpa mewajibkan intern clock in.' : 'Pilih tipe pengajuan sesuai kebutuhan. WFH tetap wajib absen; sakit dan izin tidak perlu absen setelah disetujui.' }}</p>
    </div>
    @if (! $isAdmin)
    <button class="btn btn-primary" type="button" onclick="openModal('wfhRequestModal')"><i class="fa-solid fa-paper-plane"></i> Buat Pengajuan</button>
    @endif
</section>

<div class="notification-stats">
    <article><span class="notification-stat-icon warning"><i class="fa-regular fa-clock"></i></span>
        <div><strong>{{ $stats['pending'] }}</strong><small>Menunggu</small></div>
    </article>
    <article><span class="notification-stat-icon success"><i class="fa-solid fa-check"></i></span>
        <div><strong>{{ $stats['approved'] }}</strong><small>Disetujui</small></div>
    </article>
    <article><span class="notification-stat-icon danger"><i class="fa-solid fa-xmark"></i></span>
        <div><strong>{{ $stats['rejected'] }}</strong><small>Ditolak</small></div>
    </article>
</div>

<div class="wfh-type-summary">
    @foreach ($requestTypes as $type => $meta)
    <article class="wfh-type-summary-card {{ request('type') === $type ? 'is-active' : '' }}">
        <span class="wfh-type-icon type-{{ strtolower($type) }}"><i class="fa-solid {{ $meta['icon'] }}"></i></span>
        <div>
            <strong>{{ $typeStats[$type] ?? 0 }}</strong>
            <small>{{ $meta['label'] }}</small>
        </div>
    </article>
    @endforeach
</div>

<section class="card wfh-list-card">
    <div class="attendance-panel-head">
        <div>
            <h3>{{ $isAdmin ? 'Pengajuan Intern' : 'Riwayat Pengajuan Saya' }}</h3>
            <p>{{ $isAdmin ? 'Keputusan WFH dapat diperbarui selama belum dipakai absensi. Sakit dan izin masuk recap setelah disetujui.' : 'Status dan catatan peninjauan selalu diperbarui di sini.' }}</p>
        </div>
        <form class="compact-filter d-flex gap-1 justify-content-center" action="{{ route('work-from-home.index') }}" method="GET">
            <select class="form-control" name="type" onchange="this.form.submit()">
                <option value="">Semua tipe</option>
                @foreach ($requestTypes as $type => $meta)
                <option value="{{ $type }}" @selected(request('type')===$type)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <select class="form-control" name="status" onchange="this.form.submit()">
                <option value="">Semua status</option>
                @foreach (['Pending' => 'Menunggu', 'Approved' => 'Disetujui', 'Rejected' => 'Ditolak', 'Cancelled' => 'Dibatalkan'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="wfh-request-list">
        @forelse ($requests as $wfh)
        @php
        $statusClass = match ($wfh->txtWorkFromHomeRequestStatus) { 'Approved' => 'is-approved', 'Rejected' => 'is-rejected', 'Cancelled' => 'is-cancelled', default => 'is-pending' };
        $statusLabel = match ($wfh->txtWorkFromHomeRequestStatus) { 'Approved' => 'Disetujui', 'Rejected' => 'Ditolak', 'Cancelled' => 'Dibatalkan', default => 'Menunggu review' };
        $type = $wfh->txtWorkFromHomeRequestType ?: 'WFH';
        $typeMeta = $requestTypes[$type] ?? $requestTypes['WFH'];
        @endphp
        <article class="wfh-request-card {{ $statusClass }} type-{{ strtolower($type) }}">
            <div class="wfh-request-date">
                <span>{{ $wfh->dtmWorkFromHomeRequestStartDate?->format('d') }}</span>
                <small>{{ $wfh->dtmWorkFromHomeRequestStartDate?->format('M Y') }}</small>
            </div>
            <div class="wfh-request-main">
                <div class="wfh-request-title">
                    <div>
                        <h4>{{ $isAdmin ? ($wfh->intern?->txtInternName ?? 'Intern') : $typeMeta['label'] }}</h4>
                        <p>{{ $wfh->dtmWorkFromHomeRequestStartDate?->format('d M Y') }} – {{ $wfh->dtmWorkFromHomeRequestEndDate?->format('d M Y') }}</p>
                    </div>
                    <div class="wfh-request-badges">
                        <span class="wfh-type-badge type-{{ strtolower($type) }}"><i class="fa-solid {{ $typeMeta['icon'] }}"></i> {{ $typeMeta['label'] }}</span>
                        <span class="wfh-status {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                </div>
                <p class="wfh-reason">{{ $wfh->txtWorkFromHomeRequestReason }}</p>
                <div class="wfh-request-meta">
                    <a href="{{ route('work-from-home.attachment', $wfh->intWorkFromHomeRequest_ID) }}" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip"></i> Lihat lampiran <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                    <span><i class="fa-regular fa-clock"></i> Diajukan {{ $wfh->dtmInserted?->diffForHumans() }}</span>
                </div>
                @if ($wfh->txtWorkFromHomeRequestReviewNote)
                <div class="review-note"><i class="fa-solid fa-message"></i>
                    <div><strong>Catatan peninjau</strong>
                        <p>{{ $wfh->txtWorkFromHomeRequestReviewNote }}</p>
                    </div>
                </div>
                @endif

                @if ($isAdmin && $wfh->txtWorkFromHomeRequestStatus !== 'Cancelled')
                <details class="wfh-review-panel" @if ($wfh->txtWorkFromHomeRequestStatus === 'Pending') open @endif>
                    <summary>
                        <span><i class="fa-solid {{ $wfh->txtWorkFromHomeRequestStatus === 'Pending' ? 'fa-clipboard-check' : 'fa-pen-to-square' }}"></i> {{ $wfh->txtWorkFromHomeRequestStatus === 'Pending' ? 'Tinjau pengajuan' : 'Ubah keputusan' }}</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </summary>
                    <div class="wfh-review-actions">
                        <form action="{{ route('work-from-home.approve', $wfh->intWorkFromHomeRequest_ID) }}" method="POST" @if ($wfh->txtWorkFromHomeRequestStatus === 'Rejected') data-confirm="Ubah keputusan menjadi Disetujui? Jadwal WFH akan kembali aktif." @endif>
                            @csrf @method('PATCH')
                            <input class="form-control" name="txtWorkFromHomeRequestReviewNote" value="{{ $wfh->txtWorkFromHomeRequestReviewNote }}" placeholder="Catatan persetujuan (opsional)">
                            <button class="btn btn-success btn-sm" type="submit"><i class="fa-solid fa-check"></i> {{ match ($wfh->txtWorkFromHomeRequestStatus) { 'Approved' => 'Simpan catatan', 'Rejected' => 'Ubah jadi disetujui', default => 'Setujui' } }}</button>
                        </form>
                        <form action="{{ route('work-from-home.reject', $wfh->intWorkFromHomeRequest_ID) }}" method="POST" @if ($wfh->txtWorkFromHomeRequestStatus === 'Approved') data-confirm="Batalkan persetujuan WFH ini? Intern akan kembali mengikuti WFO." @endif>
                            @csrf @method('PATCH')
                            <input class="form-control" name="txtWorkFromHomeRequestReviewNote" value="{{ $wfh->txtWorkFromHomeRequestStatus === 'Rejected' ? $wfh->txtWorkFromHomeRequestReviewNote : '' }}" placeholder="{{ $wfh->txtWorkFromHomeRequestStatus === 'Approved' ? 'Alasan pembatalan WFH' : 'Alasan penolakan' }}" required>
                            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="fa-solid {{ $wfh->txtWorkFromHomeRequestStatus === 'Approved' ? 'fa-ban' : 'fa-xmark' }}"></i> {{ match ($wfh->txtWorkFromHomeRequestStatus) { 'Approved' => 'Batalkan WFH', 'Rejected' => 'Simpan penolakan', default => 'Tolak' } }}</button>
                        </form>
                    </div>
                </details>
                @elseif ($wfh->txtWorkFromHomeRequestStatus === 'Pending')
                <form action="{{ route('work-from-home.cancel', $wfh->intWorkFromHomeRequest_ID) }}" method="POST" class="mt-3">
                    @csrf @method('PATCH')
                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="fa-solid fa-ban"></i> Batalkan pengajuan</button>
                </form>
                @endif
            </div>
        </article>
        @empty
        <div class="report-empty-state"><span><i class="fa-solid fa-house-laptop"></i></span>
            <h3>Belum ada pengajuan</h3>
            <p>Pengajuan WFH, sakit, atau izin baru akan muncul di sini.</p>
        </div>
        @endforelse
    </div>
    @if ($requests->hasPages())<div class="pagination-wrap">{{ $requests->links() }}</div>@endif
</section>
@endsection

@if (! $isAdmin)
@push('modals')
<x-crud-modal
    id="wfhRequestModal"
    title="Buat Pengajuan"
    subtitle="Pilih tipe pengajuan dan lampirkan bukti pendukung. WFH tetap wajib absensi setelah disetujui."
    size="lg"
    :active="$wfhFormHasErrors">
    <form class="form-grid form-grid-2" action="{{ route('work-from-home.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group form-span-full">
            <label class="form-label">Tipe Pengajuan <span class="required">*</span></label>
            <div class="wfh-type-options">
                @foreach ($requestTypes as $type => $meta)
                <label class="wfh-type-option type-{{ strtolower($type) }} d-flex">
                    <input type="radio" name="txtWorkFromHomeRequestType" value="{{ $type }}" @checked(old('txtWorkFromHomeRequestType', 'WFH' )===$type) required>
                    <span class="wfh-type-option-icon"><i class="fa-solid {{ $meta['icon'] }}"></i></span>
                    <span>
                        <strong>{{ $meta['label'] }}</strong>
                        <small>{{ $meta['help'] }}</small>
                    </span>
                </label>
                @endforeach
            </div>
            @error('txtWorkFromHomeRequestType')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-group">
            <label class="form-label" for="wfhStart">Tanggal Mulai <span class="required">*</span></label>
            <input id="wfhStart" class="form-control" type="date" name="dtmWorkFromHomeRequestStartDate" min="{{ now('Asia/Jakarta')->toDateString() }}" value="{{ old('dtmWorkFromHomeRequestStartDate') }}" required>
            @error('dtmWorkFromHomeRequestStartDate')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-group">
            <label class="form-label" for="wfhEnd">Tanggal Selesai <span class="required">*</span></label>
            <input id="wfhEnd" class="form-control" type="date" name="dtmWorkFromHomeRequestEndDate" min="{{ now('Asia/Jakarta')->toDateString() }}" value="{{ old('dtmWorkFromHomeRequestEndDate') }}" required>
            @error('dtmWorkFromHomeRequestEndDate')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-group form-span-full">
            <label class="form-label" for="wfhReason">Alasan <span class="required">*</span></label>
            <textarea id="wfhReason" class="form-control" name="txtWorkFromHomeRequestReason" rows="4" maxlength="1500" placeholder="Jelaskan alasan pengajuan..." required>{{ old('txtWorkFromHomeRequestReason') }}</textarea>
            @error('txtWorkFromHomeRequestReason')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-group form-span-full">
            <label class="form-label" for="wfhAttachment">Lampiran Pendukung <span class="required">*</span></label>
            <input id="wfhAttachment" class="form-control" type="file" name="txtWorkFromHomeRequestAttachment" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
            <small class="field-help">Contoh: surat sakit, agenda kegiatan, atau bukti pendukung lain.</small>
            @error('txtWorkFromHomeRequestAttachment')<small class="field-error">{{ $message }}</small>@enderror
            @error('wfh')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-actions form-span-full">
            <button class="btn btn-outline-primary" type="button" data-modal-dismiss="wfhRequestModal">Batal</button>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Kirim ke HRD / Headmaster</button>
        </div>
    </form>
</x-crud-modal>
@endpush
@endif