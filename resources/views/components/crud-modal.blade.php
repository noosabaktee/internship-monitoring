@props([
    'id' => 'crudModal',
    'title' => 'Manage Data',
    'subtitle' => null,
    'active' => false,
    'closeUrl' => null,
    'size' => 'lg',
])

@php
    $titleId = $id . 'Title';
@endphp

<div
    id="{{ $id }}"
    class="modal-overlay crud-modal-overlay {{ $active ? 'active' : '' }}"
    data-modal-overlay
    @if ($closeUrl) data-close-url="{{ $closeUrl }}" @endif
>
    <div class="modal-container modal-container-{{ $size }}" role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}">
        <div class="modal-header">
            <div class="modal-title-copy">
                <h3 id="{{ $titleId }}">{{ $title }}</h3>
                @if ($subtitle)
                    <p>{{ $subtitle }}</p>
                @endif
            </div>
            @if ($closeUrl)
                <a class="btn-close" href="{{ $closeUrl }}" aria-label="Close modal"><i class="fa-solid fa-xmark"></i></a>
            @else
                <button class="btn-close" type="button" data-modal-dismiss="{{ $id }}" aria-label="Close modal"><i class="fa-solid fa-xmark"></i></button>
            @endif
        </div>

        <div class="modal-body">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="modal-footer">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
