@extends('layouts.app', [
    'title' => 'Notifikasi - Kalbe Internship Dashboard',
    'pageTitle' => 'NOTIFIKASI',
    'pageSubtitle' => 'Pengingat penting, approval, deadline, dan perjalanan internship kamu.',
])

@section('content')
    <section class="notification-hero">
        <div>
            <span class="report-kicker"><i class="fa-solid fa-bell"></i> Notification Center</span>
            <h2>Tetap tahu hal yang perlu ditindaklanjuti.</h2>
            <p>Notifikasi disesuaikan dengan role dan hanya menampilkan informasi yang relevan untukmu.</p>
        </div>
        @if ($unreadCount > 0)
            <form action="{{ route('notifications.read-all') }}" method="POST">@csrf @method('PATCH')<button class="btn btn-light" type="submit"><i class="fa-solid fa-check-double"></i> Tandai semua dibaca</button></form>
        @endif
    </section>

    <div class="notification-toolbar">
        <div class="notification-tabs">
            <a class="{{ request('filter') !== 'unread' ? 'active' : '' }}" href="{{ route('notifications.index') }}">Semua</a>
            <a class="{{ request('filter') === 'unread' ? 'active' : '' }}" href="{{ route('notifications.index', ['filter' => 'unread']) }}">Belum dibaca <span>{{ $unreadCount }}</span></a>
        </div>
        <span class="notification-count"><i class="fa-regular fa-bell"></i> {{ $notifications->total() }} notifikasi</span>
    </div>

    <section class="notification-list">
        @forelse ($notifications as $notification)
            @php
                $icon = match ($notification->txtNotificationType) { 'project' => 'fa-calendar-check', 'wfh' => 'fa-house-laptop', 'certificate' => 'fa-certificate', 'internship' => 'fa-graduation-cap', default => 'fa-bell' };
            @endphp
            <form class="notification-item {{ $notification->dtmNotificationRead ? '' : 'is-unread' }}" action="{{ route('notifications.read', $notification->intNotification_ID) }}" method="POST">
                @csrf @method('PATCH')
                <button type="submit">
                    <span class="notification-item-icon type-{{ $notification->txtNotificationType }}"><i class="fa-solid {{ $icon }}"></i></span>
                    <span class="notification-item-copy">
                        <span class="notification-item-title">{{ $notification->txtNotificationTitle }} @if(!$notification->dtmNotificationRead)<i></i>@endif</span>
                        <span class="notification-item-message">{{ $notification->txtNotificationMessage }}</span>
                        <span class="notification-item-time"><i class="fa-regular fa-clock"></i> {{ $notification->dtmInserted?->diffForHumans() }}</span>
                    </span>
                    <span class="notification-item-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
            </form>
        @empty
            <div class="report-empty-state"><span><i class="fa-regular fa-bell-slash"></i></span><h3>Semuanya tenang</h3><p>Tidak ada notifikasi pada filter ini.</p></div>
        @endforelse
    </section>

    @if ($notifications->hasPages())<div class="pagination-wrap">{{ $notifications->links() }}</div>@endif
@endsection
