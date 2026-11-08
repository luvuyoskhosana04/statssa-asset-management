@props([
    'types' => config('filesystems.allowed_upload_extensions'),
    'maxBytes' => null,
    'combined' => false,
])
@php
    $maxBytes = $maxBytes ?? \App\Helpers\Helper::file_upload_max_size();
    $mimeExtensions = [
        'image/jpeg' => '.jpg, .jpeg', 'image/gif' => '.gif', 'image/png' => '.png',
        'image/webp' => '.webp', 'image/svg' => '.svg', 'image/svg+xml' => '.svg',
        'image/avif' => '.avif', 'image/x-icon' => '.ico', 'image/vnd.microsoft.icon' => '.ico',
        'text/xml' => '.xml', 'application/xml' => '.xml', 'application/zip' => '.zip',
    ];
    $extensions = [];
    foreach (explode(',', $types) as $type) {
        $extensions[] = $mimeExtensions[trim($type)] ?? trim($type);
    }
    $accepted = implode(', ', array_unique(array_filter(array_map('trim', explode(',', implode(',', $extensions))))));
    $unit = $maxBytes >= 1048576 ? 'MB' : ($maxBytes >= 1024 ? 'KB' : 'bytes');
    $divisor = $maxBytes >= 1048576 ? 1048576 : ($maxBytes >= 1024 ? 1024 : 1);
    $size = rtrim(rtrim(number_format($maxBytes / $divisor, 1, '.', ''), '0'), '.').' '.$unit;
@endphp
<p {{ $attributes->merge(['class' => 'help-block upload-requirements']) }} data-upload-guidance data-upload-max-bytes="{{ $maxBytes }}" data-upload-types="{{ $accepted }}">
    @if ($maxBytes > 0)
        {{ trans($combined ? 'presentation.form_upload_rules_combined' : 'presentation.form_upload_rules', ['types' => $accepted, 'size' => $size]) }}
    @else
        {{ trans('presentation.form_upload_server_limit') }}
    @endif
</p>
