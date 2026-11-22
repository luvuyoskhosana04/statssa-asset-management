@props(['label' => trans('presentation.form_supporting_information')])

<details {{ $attributes->class(['form-support-disclosure']) }}>
    <summary>{{ $label }}</summary>
    <div class="form-support-body">
        {{ $slot }}
    </div>
</details>
