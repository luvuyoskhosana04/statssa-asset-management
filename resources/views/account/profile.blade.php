@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('general.editprofile') }}
@stop

{{-- Account page content --}}
@section('content')


<x-container class="col-md-6 col-md-offset-3 profile-editor">
  <x-form route="{{ route('profile.update') }}">
    <x-box>
          <input type="hidden" name="gravatar" value="{{ old('gravatar', $user->gravatar) }}">

          @can('self.profile')
          <section class="profile-photo" aria-labelledby="profile-photo-label">
              <h2 id="profile-photo-label">Profile picture</h2>
              <div class="profile-photo-controls">
                  <img id="profile-photo-preview" src="{{ $user->present()->gravatar() }}" alt="{{ $user->display_name }} profile picture" width="80" height="80">
                  <div class="profile-photo-actions">
                  <button type="button" class="btn btn-primary" id="profile-change-photo">Change picture</button>
                  @if ($user->avatar)
                      <button type="button" class="btn btn-danger" id="profile-delete-photo" aria-pressed="false">Delete picture</button>
                  @endif
                  </div>
              </div>
              <input type="file" name="avatar" id="profile-avatar" class="sr-only" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif" aria-label="Change profile picture" aria-describedby="profile-photo-rules profile-photo-status">
              <input type="hidden" name="image_delete" id="profile-image-delete" value="0">
              <x-input.upload-guidance id="profile-photo-rules" types=".jpg, .jpeg, .png, .gif, .webp, .svg, .avif" />
              <p id="profile-photo-status" class="help-block" role="status"></p>
              <x-form.error name="avatar" />
              <x-form.error name="image_delete" />
          </section>

          <fieldset name="user-preferences">
              <x-form.legend class="sr-only">
                  {{ trans('admin/settings/general.legends.your_details') }}
              </x-form.legend>
                    <x-form.row
                        :label="trans('general.first_name')"
                        :item="$user"
                        name="first_name"
                    />

                    <x-form.row
                        :label="trans('general.last_name')"
                        :item="$user"
                        name="last_name"
                    />

                    @can('self.edit_location')
                        <x-input.location-select
                            :label="trans('general.location')"
                            name="location_id"
                            :selected="old('location_id', $user->location_id)"
                        />

                        @if ($snipeSettings->full_multiple_companies_support == '1' && $snipeSettings->scope_locations_fmcs == '1')
                            @cannot('superadmin')
                                <div class="col-md-8 col-md-offset-3">
                                    <x-form.help name="location_id_fmcs" icon="tip">{{ trans('general.fmcs_location_select_note') }}</x-form.help>
                                </div>
                            @endcannot
                        @endif
                    @endcan

                    <x-form.row
                        :label="trans('admin/users/table.phone')"
                        :item="$user"
                        name="phone"
                        type="tel"
                        placeholder="+27 82 123 4567"
                        help_text="South Africa (+27). Enter the number without the leading 0 after +27."
                        input_icon="phone"
                        input_group_addon="left"
                    />

                    <x-form.row
                        :label="trans('general.website')"
                        :item="$user"
                        name="website"
                        type="url"
                        input_icon="link"
                        input_group_addon="left"
                        placeholder="https://example.com"
                    />


                    @if ($snipeSettings->two_factor_enabled == '1')
                        <x-form.checkbox-row
                            name="two_factor_optin"
                            :label="trans('admin/settings/general.two_factor_enabled_text')"
                            :item="$user"
                            :disabled="auth()->user()->cannot('self.two_factor')"
                        />
                        <div class="col-md-8 col-md-offset-3">
                            @can('self.two_factor')
                                <x-form.help name="two_factor_optin">{{ trans('admin/settings/general.two_factor_enabled_warning') }}</x-form.help>
                            @else
                                <x-form.help name="two_factor_optin">{{ trans('admin/settings/general.two_factor_enabled_edit_not_allowed') }}</x-form.help>
                            @endcan
                            <x-demo-lock>{{ trans('general.feature_disabled') }}</x-demo-lock>
                        </div>
                    @endif
          </fieldset>
          @endcan





    </x-box>
  </x-form>
</x-container>

@stop


@push('js')
<script nonce="{{ csrf_token() }}">
(function () {
    var input = document.getElementById('profile-avatar');
    if (!input) return;
    var preview = document.getElementById('profile-photo-preview');
    var initialSrc = preview.src;
    var status = document.getElementById('profile-photo-status');
    var remove = document.getElementById('profile-delete-photo');
    var deletion = document.getElementById('profile-image-delete');
    var previewUrl;
    function releasePreview() {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    }
    document.getElementById('profile-change-photo').addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () {
        var file = input.files[0];
        if (!file) return;
        releasePreview();
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.classList.remove('is-removed');
        deletion.value = '0';
        if (remove) { remove.textContent = 'Delete picture'; remove.setAttribute('aria-pressed', 'false'); }
        status.textContent = file.name + ' selected. Save to apply your new picture.';
    });
    if (remove) remove.addEventListener('click', function () {
        var deleting = deletion.value !== '1';
        deletion.value = deleting ? '1' : '0';
        releasePreview();
        input.value = '';
        preview.src = initialSrc;
        preview.classList.toggle('is-removed', deleting);
        remove.textContent = deleting ? 'Undo removal' : 'Delete picture';
        remove.setAttribute('aria-pressed', String(deleting));
        status.textContent = deleting ? 'Your picture will be removed when you save.' : '';
        deletion.dispatchEvent(new Event('change', {bubbles: true}));
    });
    window.addEventListener('pagehide', releasePreview);
})();
</script>
@endpush
