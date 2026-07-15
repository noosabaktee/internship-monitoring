@extends('layouts.app', [
    'title' => 'Pengajuan WFH - Kalbe Internship Dashboard',
    'pageTitle' => 'PENGAJUAN WFH',
    'pageSubtitle' => $isAdmin ? 'Review pengajuan kerja dari rumah intern.' : 'Ajukan jadwal WFH sebelum melakukan absensi dari luar kantor.',
])

@section('content')
    <section class="wfh-hero">
        <div>
            <span class="report-kicker"><i class="fa-solid fa-house-laptop"></i> Work From Home</span>
            <h2>{{ $isAdmin ? 'Kelola persetujuan WFH dengan cepat dan transparan.' : 'WFH tetap produktif, tetap tercatat.' }}</h2>
            <p>{{ $isAdmin ? 'HRD/Headmaster dapat meninjau lampiran serta memperbarui keputusan selama pengajuan belum dipakai untuk absensi WFH.' : 'WFH hanya aktif pada tanggal yang disetujui. Face ID dan lokasi perangkat tetap dicatat, tetapi tanpa batas radius kantor.' }}</p>
        </div>
        @if (! $isAdmin)
            <a class="btn btn-primary" href="#wfhRequestForm"><i class="fa-solid fa-paper-plane"></i> Buat Pengajuan</a>
        @endif
    </section>

    <div class="notification-stats">
        <article><span class="notification-stat-icon warning"><i class="fa-regular fa-clock"></i></span><div><strong>{{ $stats['pending'] }}</strong><small>Menunggu</small></div></article>
        <article><span class="notification-stat-icon success"><i class="fa-solid fa-check"></i></span><div><strong>{{ $stats['approved'] }}</strong><small>Disetujui</small></div></article>
        <article><span class="notification-stat-icon danger"><i class="fa-solid fa-xmark"></i></span><div><strong>{{ $stats['rejected'] }}</strong><small>Ditolak</small></div></article>
    </div>

    @if (! $isAdmin)
        <section class="card form-section-card" id="wfhRequestForm">
            <div class="attendance-panel-head">
                <div><h3>Ajukan Jadwal WFH</h3><p>Lampiran wajib berupa PDF atau gambar, maksimal 5 MB.</p></div>
                <span class="attendance-map-chip"><i class="fa-solid fa-circle-info"></i> Bukan pengajuan cuti</span>
            </div>
            <form class="form-grid form-grid-2" action="{{ route('work-from-home.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="wfhStart">Mulai WFH <span class="required">*</span></label>
                    <input id="wfhStart" class="form-control" type="date" name="dtmWorkFromHomeRequestStartDate" min="{{ now('Asia/Jakarta')->toDateString() }}" value="{{ old('dtmWorkFromHomeRequestStartDate') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="wfhEnd">Selesai WFH <span class="required">*</span></label>
                    <input id="wfhEnd" class="form-control" type="date" name="dtmWorkFromHomeRequestEndDate" min="{{ now('Asia/Jakarta')->toDateString() }}" value="{{ old('dtmWorkFromHomeRequestEndDate') }}" required>
                </div>
                <div class="form-group form-span-full">
                    <label class="form-label" for="wfhReason">Alasan <span class="required">*</span></label>
                    <textarea id="wfhReason" class="form-control" name="txtWorkFromHomeRequestReason" rows="4" maxlength="1500" placeholder="Jelaskan alasan dan rencana kerja selama WFH..." required>{{ old('txtWorkFromHomeRequestReason') }}</textarea>
                </div>
                <div class="form-group form-span-full">
                    <label class="form-label" for="wfhAttachment">Lampiran Pendukung <span class="required">*</span></label>
                    <input id="wfhAttachment" class="form-control" type="file" name="txtWorkFromHomeRequestAttachment" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                    <small class="field-help">Contoh: surat, agenda kegiatan, atau bukti pendukung lain.</small>
                </div>
                <div class="form-actions form-span-full"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Kirim ke HRD / Headmaster</button></div>
            </form>
        </section>
    @endif

    <section class="card wfh-list-card">
        <div class="attendance-panel-head">
            <div><h3>{{ $isAdmin ? 'Pengajuan Intern' : 'Riwayat Pengajuan Saya' }}</h3><p>{{ $isAdmin ? 'Keputusan yang sudah diberikan masih dapat diperbarui sebelum dipakai untuk absensi WFH.' : 'Status dan catatan peninjauan selalu diperbarui di sini.' }}</p></div>
            <form class="compact-filter" action="{{ route('work-from-home.index') }}" method="GET">
                <select class="form-control" name="status" onchange="this.form.submit()">
                    <option value="">Semua status</option>
                    @foreach (['Pending' => 'Menunggu', 'Approved' => 'Disetujui', 'Rejected' => 'Ditolak', 'Cancelled' => 'Dibatalkan'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="wfh-request-list">
            @forelse ($requests as $wfh)
                @php
                    $statusClass = match ($wfh->txtWorkFromHomeRequestStatus) { 'Approved' => 'is-approved', 'Rejected' => 'is-rejected', 'Cancelled' => 'is-cancelled', default => 'is-pending' };
                    $statusLabel = match ($wfh->txtWorkFromHomeRequestStatus) { 'Approved' => 'Disetujui', 'Rejected' => 'Ditolak', 'Cancelled' => 'Dibatalkan', default => 'Menunggu review' };
                @endphp
                <article class="wfh-request-card {{ $statusClass }}">
                    <div class="wfh-request-date">
                        <span>{{ $wfh->dtmWorkFromHomeRequestStartDate?->format('d') }}</span>
                        <small>{{ $wfh->dtmWorkFromHomeRequestStartDate?->format('M Y') }}</small>
                    </div>
                    <div class="wfh-request-main">
                        <div class="wfh-request-title">
                            <div>
                                <h4>{{ $isAdmin ? ($wfh->intern?->txtInternName ?? 'Intern') : 'Work From Home' }}</h4>
                                <p>{{ $wfh->dtmWorkFromHomeRequestStartDate?->format('d M Y') }} – {{ $wfh->dtmWorkFromHomeRequestEndDate?->format('d M Y') }}</p>
                            </div>
                            <span class="wfh-status {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>
                        <p class="wfh-reason">{{ $wfh->txtWorkFromHomeRequestReason }}</p>
                        <div class="wfh-request-meta">
                            <a href="{{ route('work-from-home.attachment', $wfh->intWorkFromHomeRequest_ID) }}" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip"></i> Lihat lampiran <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                            <span><i class="fa-regular fa-clock"></i> Diajukan {{ $wfh->dtmInserted?->diffForHumans() }}</span>
                        </div>
                        @if ($wfh->txtWorkFromHomeRequestReviewNote)
                            <div class="review-note"><i class="fa-solid fa-message"></i><div><strong>Catatan peninjau</strong><p>{{ $wfh->txtWorkFromHomeRequestReviewNote }}</p></div></div>
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
                <div class="report-empty-state"><span><i class="fa-solid fa-house-laptop"></i></span><h3>Belum ada pengajuan WFH</h3><p>Pengajuan baru akan muncul di sini.</p></div>
            @endforelse
        </div>
        @if ($requests->hasPages())<div class="pagination-wrap">{{ $requests->links() }}</div>@endif
    </section>
@endsection
