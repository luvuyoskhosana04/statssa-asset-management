<!-- {{ $logoVariable }}logo image upload -->

<div class="form-group">
    <div class="col-md-3 control-label{!! $errors->has($logoVariable) ? ' error' : '' !!}">
        <strong>
        {{ trans($logoLabel) }}
        </strong>
    </div>
    <div class="col-md-9">
        <label class="btn btn-theme{{ (config('app.lock_passwords')) ? ' disabled' : '' }}">
            {{ trans('button.select_file')  }}
            <input type="file" name="{{ $logoVariable }}" class="js-uploadFile" id="{{ $logoId }}"
                   accept="{{  $allowedTypes ?? "image/gif,image/jpeg,image/webp,image/png,image/svg,image/svg+xml,image/avif" }}"
                   data-maxsize="{{ $maxSize ?? Helper::file_upload_max_size() }}"
                   style="display:none; max-width: 90%"{{ (config('app.lock_passwords')) ? ' disabled' : '' }}>
        </label>

        <span class='label label-default' id="{{ $logoId }}-info"></span>

        <x-form.error :name="$logoVariable" />


        <p class="help-block upload-status" id="{{ $logoId }}-status"></p>
        <x-input.upload-guidance :types="$allowedTypes ?? 'image/gif,image/jpeg,image/webp,image/png,image/svg,image/svg+xml,image/avif'" :max-bytes="$maxSize ?? Helper::file_upload_max_size()" />
        @php
            $extraHelp = trim(str_replace([trans('general.image_filetypes_help', ['size' => Helper::file_upload_max_size_readable()]), trans('admin/settings/general.favicon_format')], '', $helpBlock));
        @endphp
        @if ($extraHelp)<p class="help-block">{{ $extraHelp }}</p>@endif
        @if ($logoVariable === 'favicon')<p class="help-block">{{ \Illuminate\Support\Str::after(trans('admin/settings/general.favicon_format'), '. ') }}</p>@endif

        <x-demo-lock>{{ trans('general.feature_disabled') }}</x-demo-lock>
    </div>

    <div class="col-md-9 col-md-offset-3">

            @if (($setting->$logoVariable!='') && (Storage::disk('public')->exists(($logoPath ?? ''). $snipeSettings->$logoVariable)))
                <div class="pull-left {{ $previewClass ?? '' }}" style="padding-right: 20px;">
                    <a href="{{ Storage::disk('public')->url(e(($logoPath ?? '').$snipeSettings->$logoVariable)) }}"{!! ($logoVariable!='favicon') ? ' data-toggle="lightbox"' : '' !!} title="Existing logo">
                        <img style="height: 80px; padding-bottom: 5px;" alt="Current logo" src="{{ Storage::disk('public')->url(e(($logoPath ?? ''). $snipeSettings->$logoVariable)) }}">
                    </a>
                </div>
            @endif

            <div id="{{ $logoId }}-previewContainer" style="display: none;" class="{{ $previewClass ?? '' }}">
                <img id="{{ $logoId }}-imagePreview" style="height: 80px;" alt="Logo upload preview">
            </div>



    </div>
    @if (($setting->$logoVariable!='') && (Storage::disk('public')->exists(($logoPath ?? '').$snipeSettings->$logoVariable)))

    <div class="col-md-9 col-md-offset-3">
        <label id="{{ $logoId }}-deleteCheckbox" for="{{ $logoClearVariable }}" style="font-weight: normal" class="form-control">
            <input type="checkbox" name="{{ $logoClearVariable }}" value="1" @checked(old($logoClearVariable))>
            {{ trans('general.remove_current_image', ['type' => $logoLabel]) }}
        </label>
    </div>
    @endif



</div>







