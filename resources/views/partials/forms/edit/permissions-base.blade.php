@php
    $oldPermissions = old('permission');
    $permissionUser = ($use_inherit && isset($user) && $user->exists) ? $user : null;
@endphp
<div class="permission-editor" data-permission-editor>
    <div class="permission-tools">
        <label class="permission-search">{{ trans('presentation.search_permissions') }}
            <input type="search" class="form-control" data-permission-search placeholder="{{ trans('presentation.search_permissions') }}">
        </label>
        <label class="permission-changed"><input type="checkbox" data-permission-changed> {{ trans('presentation.changed_only') }}</label>
        <button type="button" class="btn btn-default btn-sm" data-permission-expand>{{ trans('presentation.expand_all') }}</button>
        <button type="button" class="btn btn-default btn-sm" data-permission-collapse>{{ trans('presentation.collapse_all') }}</button>
    </div>
    <p class="help-block">{{ trans('presentation.permission_help') }}</p>
    @if ($use_inherit)
        <p class="help-block">{{ trans('presentation.saved_access') }}: {{ trans('presentation.permission_scope') }}</p>
    @endif
    <p class="alert alert-info" data-permission-dirty hidden role="status">{{ trans('presentation.unsaved') }}</p>
    <p data-permission-empty hidden>{{ trans('presentation.none_found') }}</p>
    @foreach ($permissions as $main_section => $sectionPermissions)
        @php
            $sectionPermission = $sectionPermissions[0];
            $area = str_slug($main_section);
            $sectionName = $area === 'reports' ? 'reports.view' : $area;
            $sectionTitle = trans('permissions.'.$area.'.name');
            $sectionRows = count($sectionPermissions) > 2 ? array_values(array_filter($sectionPermissions, fn ($permission) => $permission['display'])) : [];
        @endphp
        @if (($area === 'superuser' && !auth()->user()->isSuperUser()) || ($area === 'admin' && !auth()->user()->hasAccess('admin')))
            @continue
        @endif
        <details class="permission-section {{ $area !== 'superuser' ? 'nonsuperuser' : '' }} {{ !in_array($area, ['superuser', 'admin']) ? 'nonadmin' : '' }}" data-permission-section @if ($loop->first) open @endif>
            <summary><span>{{ $sectionTitle }}</span><span class="permission-section-count" data-permission-count>{{ count($sectionRows) ?: 1 }}</span></summary>
            @if (Lang::has('permissions.'.$area.'.note'))
                <p class="permission-section-note">{{ trans('permissions.'.$area.'.note') }}</p>
            @endif
            <div class="permission-matrix-wrap">
                <table class="permission-matrix">
                    <thead><tr>
                        <th scope="col">{{ trans('presentation.capability') }}</th>
                        <th scope="col">{{ trans('presentation.allow') }}</th>
                        @if ($use_inherit)<th scope="col">{{ trans('presentation.inherit') }}</th>@endif
                        <th scope="col">{{ trans('presentation.deny') }}</th>
                        @if ($use_inherit)<th scope="col">{{ trans('presentation.effective') }}</th><th scope="col">{{ trans('presentation.source') }}</th>@endif
                    </tr></thead>
                    <tbody>
                    @foreach (array_merge([['permission' => $sectionName, 'title' => $sectionRows ? trans('presentation.section_all') : $sectionTitle, 'header' => true]], array_map(fn ($permission) => array_merge($permission, ['title' => trans('permissions.'.str_slug($permission['permission']).'.name'), 'header' => false]), $sectionRows)) as $permissionRow)
                        @php
                            $key = $permissionRow['permission'];
                            $current = is_array($oldPermissions) && array_key_exists($key, $oldPermissions) ? $oldPermissions[$key] : ($groupPermissions[$key] ?? ($use_inherit ? 0 : null));
                            $effective = $permissionUser ? $permissionUser->hasAccess($key) : null;
                            $source = trans('presentation.default_source');
                            if ($permissionUser) {
                                if ($permissionUser->isSuperUser()) {
                                    $source = trans('presentation.superuser_source');
                                } elseif (in_array((string) ($groupPermissions[$key] ?? '0'), ['1', '-1'], true)) {
                                    $source = trans('presentation.direct');
                                } else {
                                    $grantingGroups = $permissionUser->groups->filter(fn ($group) => ((array) json_decode($group->permissions, true))[$key] ?? false)->filter(fn ($group) => (string) (((array) json_decode($group->permissions, true))[$key] ?? 0) === '1')->pluck('name');
                                    if ($grantingGroups->isNotEmpty()) {
                                        $source = $grantingGroups->implode(', ');
                                    }
                                }
                            }
                        @endphp
                        <tr class="{{ $permissionRow['header'] ? 'header-row permission-bulk-row' : '' }}" data-permission-row data-permission-key="{{ $key }}">
                            <th scope="row">
                                {{ $permissionRow['title'] }}
                                @if (!$permissionRow['header'] && Lang::has('permissions.'.str_slug($key).'.note'))
                                    <small>{{ trans('permissions.'.str_slug($key).'.note') }}</small>
                                @endif
                            </th>
                            @foreach ([1 => 'allow', 0 => 'inherit', -1 => 'deny'] as $value => $state)
                                @if ($value === 0 && !$use_inherit) @continue @endif
                                <td><label class="permission-choice">
                                    <input type="radio" name="permission[{{ $key }}]" value="{{ $value }}"
                                           id="{{ $permissionRow['header'] ? $area : str_slug($key) }}_{{ $state }}"
                                           class="{{ $state }} {{ $permissionRow['header'] ? $area : 'radiochecker-'.$area }}"
                                           @if ($permissionRow['header']) data-checker-group="{{ $area }}" @endif
                                           aria-label="{{ $permissionRow['title'] }}: {{ trans('presentation.'.$state) }}"
                                           @checked($current !== null && (string) $current === (string) $value)>
                                    <span>{{ trans('presentation.'.$state) }}</span>
                                </label></td>
                            @endforeach
                            @if ($use_inherit)
                                <td class="permission-effective">{{ $effective === null ? trans('presentation.on_save') : trans('presentation.'.($effective ? 'allowed' : 'denied')) }}</td>
                                <td class="permission-source">{{ $effective === null ? trans('presentation.new_account') : $source }}</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endforeach
</div>
