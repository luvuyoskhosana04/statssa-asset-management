@section('title')
    {{ trans('general.import') }}
    @parent
@stop
<div class="import-workspace">
    {{-- Livewire requires a 'master' <div>, above --}}
        <div class="row">

{{-- alert --}}
@if($message != '')
    <div class="col-md-12" class="{{ $message_type }}">
        <x-alert :type="$this->message_type">
            <button type="button" class="close" wire:click="$set('message','')">&times;</button>
            @if($message_type == 'success')
                <i class="fas fa-check faa-pulse animated" aria-hidden="true"></i>
            @endif
            <strong>{{-- title --}} </strong>
            {{ $message }}
        </x-alert>
    </div>
@endif

@if($this->showFmcsRestrictionNotice)
    <div class="col-md-12">
        <x-alert type="info" icon="tip">
            {{ trans('general.fmcs_import_restriction_note') }}
        </x-alert>
    </div>
@endif

        @if($import_errors)
          <div class="col-md-12">
            <div class="box box-default">
                <div class="box-body">
                    <x-alert type="warning" icon="warning">
                        <strong>{{ trans('general.warning', ['warning'=> trans('general.errors_importing')]) }}</strong>
                    </x-alert>

                    <div class="errors-table">
                        <table class="table table-striped table-bordered" id="errors-table">
                            <thead>
                            <th scope="col">{{ trans('general.item') }}</th>
                            <th scope="col">{{ trans('admin/custom_fields/general.field') }}</th>
                            <th scope="col">{{ trans('general.error') }}</th>
                            </thead>
                            <tbody>
                            @foreach($import_errors AS $key => $actual_import_errors)
                                @foreach($actual_import_errors AS $table => $error_bag)
                                    {{-- general messages such as "The selected file is invalid" are simple strings --}}
                                    @if(is_string($error_bag))
                                        <tr>
                                            <td></td>
                                            <td></td>
                                            <td>{{ $error_bag }}</td>
                                        </tr>
                                    @elseif(is_array($error_bag))
                                        @foreach($error_bag as $field => $error_list)
                                            <tr>
                                                <td><b>{{ $key }}</b></td>
                                                <td><b>{{ $field }}</b></td>
                                                <td>
                                                    <span>{{ implode(", ",$error_list) }}</span>
                                                    <br />
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
       </div>
@endif

            <div class="col-md-9">
                <div class="box box-default">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-12 table-responsive">

                                @if ($this->files->isEmpty())
                                    {{-- Nothing to list yet. Hide the whole
                                         table so an empty <thead> doesn't
                                         render as a stub, and point the user
                                         at the upload widget in the sidebar. --}}
                                    <div class="text-center text-muted" style="padding: 40px 20px;">
                                        <p style="font-size: 16px; margin-bottom: 0;">
                                            {{ trans('general.no_import_files_yet') }}
                                        </p>
                                    </div>
                                @else

                                    @if (count($selectedIds) > 0)
                                    <div class="row" wire:key="import-bulk-actions" style="padding-bottom: 10px;">
                                        <div class="col-md-12">
                                            <button type="button"
                                                    class="btn btn-danger"
                                                    data-toggle="modal"
                                                    data-target="#bulkDeleteImportsModal"
                                                @disabled(config('app.lock_passwords') || count($selectedIds) === 0)>
                                                <i class="fas fa-trash" aria-hidden="true"></i>
                                                {{ trans('admin/hardware/message.import.bulk_delete.button', ['count' => count($selectedIds)]) }}
                                            </button>
                                        </div>
                                    </div>

                                    @endif

                                <table data-id-table="upload-table"
                                        data-side-pagination="client"
                                        id="upload-table"
                                        class="col-md-12 table table-striped snipe-table">

                                    <tr>
                                        <th class="col-md-1">
                                            <label class="sr-only" for="importsSelectAll">
                                                {{ trans('admin/hardware/message.import.bulk_delete.select_all') }}
                                            </label>
                                            <label class="checkbox-target">
                                            <input type="checkbox"
                                                    id="importsSelectAll"
                                                    wire:model.live="selectAll"
                                                    aria-label="{{ trans('admin/hardware/message.import.bulk_delete.select_all') }}">
                                            </label><span class="import-select-all-text">{{ trans('general.select_all') }}</span>
                                        </th>
                                        <th>
                                            {{ trans('general.file_name') }}
                                        </th>
                                        <th>
                                            {{ trans('general.created_at') }}
                                        </th>
                                        <th>
                                            {{ trans('general.created_by') }}
                                        </th>

                                        <th>
                                            {{ trans('general.filesize') }}
                                        </th>
                                        <th class="col-md-1 text-right">
                                            <span class="sr-only">{{ trans('general.actions') }}</span>
                                        </th>
                                    </tr>

                                    @foreach($this->files as $currentFile)

                                        <tr wire:key="import-row-{{ $currentFile->id }}" style="{{ ($this->activeFile && ($currentFile->id == $this->activeFile->id)) ? 'font-weight: bold' : '' }}">
                                                <td>
                                                    <label class="sr-only" for="import-row-{{ $currentFile->id }}">
                                                        {{ trans('admin/hardware/message.import.bulk_delete.select_row', ['file' => $currentFile->file_path]) }}
                                                    </label>
                                                    <label class="checkbox-target">
                                                    <input type="checkbox"
                                                            id="import-row-{{ $currentFile->id }}"
                                                            wire:model.live="selectedIds"
                                                            value="{{ $currentFile->id }}"
                                                            @disabled(! $this->canDeleteFile($currentFile))
                                                            aria-label="{{ trans('admin/hardware/message.import.bulk_delete.select_row', ['file' => $currentFile->file_path]) }}">
                                                    </label>
                                                </td>
                                    			<td>

                                                    @if ($this->fileMissingOnDisk($currentFile))
                                                        <span class="text-danger" style="text-decoration: line-through;" data-tooltip="true" title="{{ trans('general.file_does_not_exist') }}">
                                                            <x-icon type="x" /> {{ $currentFile->file_path }}
                                                        </span>
                                                    @elseif ((auth()->user()->id == $currentFile->adminuser?->id) || (auth()->user()->isSuperUser()))
                                                        <a href="{{ route('imports.download', $currentFile) }}">{{ $currentFile->file_path }}</a>
                                                    @else
                                                        {{ $currentFile->file_path }}
                                                    @endif
                                                    @if (in_array($currentFile->id, $newlyUploadedIds, true))
                                                        {{-- Yellow spinning star flags rows the user
                                                             just uploaded. Faster spin than fa-spin's
                                                             default 2s via inline animation-duration,
                                                             and an opacity transition so the JS
                                                             timeout can add .newly-uploaded-fading
                                                             for a soft dissolve before Livewire
                                                             removes the DOM. --}}
                                                        <i class="fas fa-star fa-spin text-yellow newly-uploaded-star" style="margin-left: 6px; animation-duration: 0.7s; transition: opacity 0.6s ease-out;" aria-label="{{ trans('general.newly_uploaded') }}" data-tooltip="true" data-title="{{ trans('general.newly_uploaded') }}"></i>
                                                    @endif
                                                </td>
                                                <td data-import-label="{{ trans('general.created_at') }}">{{ Helper::getFormattedDateObject($currentFile->created_at, 'datetime', false) }}</td>
                                                <td data-import-label="{{ trans('general.created_by') }}">
                                                    @if ($currentFile->adminuser)
                                                        @can('view', $currentFile->adminuser)
                                                            <a href="{{ route('users.show', $currentFile->adminuser) }}">{{ $currentFile->adminuser->display_name }}</a>
                                                        @else
                                                            {{ $currentFile->adminuser->display_name }}
                                                        @endcan
                                                    @else
                                                        ---
                                                    @endif

                                                </td>
                                                <td data-import-label="{{ trans('general.filesize') }}">{{ Helper::formatFilesizeUnits($currentFile->filesize) }}</td>
                                                <td class="col-md-1 text-right" style="white-space: nowrap;">
                                                    @if ($this->fileMissingOnDisk($currentFile))
                                                        <button class="btn btn-sm btn-info import-configure-button disabled" disabled data-tooltip="true" data-title="{{ trans('general.file_does_not_exist') }}">
                                                            <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                                                            <span>{{ trans('presentation.import_configure') }}</span>
                                                        </button>
                                                    @else
                                                        <button class="btn btn-sm btn-info import-configure-button" wire:click="selectFile({{ $currentFile->id }})" data-tooltip="true" data-title="{{ trans('general.import_this_file') }}">
                                                            <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                                                            <span>{{ trans('presentation.import_configure') }}</span>
                                                        </button>
                                                    @endif

                                                    @if (((auth()->user()->id == $currentFile->adminuser?->id) || (auth()->user()->isSuperUser())) && ! config('app.lock_passwords'))
                                                        <a href="#" wire:click.prevent="$set('activeFileId',null)" data-tooltip="true" data-title="{{ trans('general.delete') }}">
                                                            <button class="btn btn-sm btn-danger" wire:click="destroy({{ $currentFile->id }})" wire:loading.attr="disabled" wire:target="destroy({{ $currentFile->id }})">
                                                                <i class="fas fa-trash icon-white" aria-hidden="true" wire:loading.remove wire:target="destroy({{ $currentFile->id }})"></i>
                                                                <i class="fas fa-spinner fa-spin icon-white" aria-hidden="true" wire:loading wire:target="destroy({{ $currentFile->id }})"></i>
                                                                <span class="sr-only">{{ trans('general.delete') }}</span>
                                                            </button>
                                                        </a>
                                                    @else
                                                        <a data-tooltip="true" class="btn btn-sm btn-danger disabled" data-title="{{ trans('general.delete') }}">
                                                            <i class="fas fa-trash icon-white" aria-hidden="true"></i>
                                                            <span class="sr-only">{{ trans('general.delete') }}</span>
                                                        </a>
                                                    @endif

                                    			</td>
                                    		</tr>
                                    @endforeach
                                </table>

                                @if ($this->files->hasPages())
                                    <div class="row">
                                        <div class="col-md-12">
                                            {{ $this->files->links() }}
                                        </div>
                                    </div>
                                @endif

                                @endif {{-- $this->files->isEmpty() --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="box box-default import-upload-panel">
                    <div class="box-header with-border">
                        <h2 class="box-title" style="margin: 0;">{{ trans('presentation.import_upload_title') }}</h2>
                    </div>
                    <div class="box-body">

                        <div class="import-upload-intro">
                        <p>{!! trans('general.importing_help') !!}</p>
                        <p class="help-block">{{ trans('presentation.upload_stage') }}</p>

                        {{-- Feature-level demo-mode notice for the whole
                             importer path (upload, process, destroy).
                             x-demo-lock self-gates on editableOnDemo. --}}
                        <x-demo-lock style="width: 100%;">{{ trans('general.feature_disabled') }}</x-demo-lock>

                        <div id="import-upload-zone" class="import-upload-zone">
                        <p class="import-drop-heading">{{ trans('presentation.import_drop') }}</p>
                        <p class="help-block">{{ trans('presentation.import_upload_help', ['size' => Helper::file_upload_max_size_readable()]) }}</p>
                        {{-- Upload button. The fileupload jQuery widget is
                             bound to #fileupload down in @script, so the
                             input still has to be present in the DOM even
                             when lock_passwords is set. --}}
                        @if (! config('app.lock_passwords'))
                            <span class="btn btn-theme btn-block fileinput-button" style="margin-bottom: 15px;">
                                <span>{{ trans('button.select_file') }}</span>
                                <label for="files[]"><span class="sr-only">{{ trans('admin/importer/general.select_file') }}</span></label>
                                {{-- multiple lets users pick a batch of CSVs
                                     in one dialog; jQuery fileupload fires
                                     add()/progress()/done() per file, so the
                                     Livewire callbacks already handle each
                                     one individually. Server-side
                                     ImportController::store already iterates
                                     Request::file('files'). --}}
                                <input id="fileupload" type="file" name="files[]" multiple data-url="{{ route('api.imports.index') }}" accept=".csv,.tsv,.txt,text/csv,text/plain,text/tab-separated-values" aria-label="{{ trans('button.select_file') }}">
                            </span>
                        @endif

                        <x-input.upload-guidance types=".csv, .tsv, .txt" />
                        <p class="help-block">{{ trans('presentation.import_upload_safe') }}</p>
                        </div>
                        </div>
                        <div id="import-upload-activity" class="import-activity" wire:ignore hidden>
                            <div class="import-activity-ring" role="progressbar" aria-label="{{ trans('general.uploading') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <svg viewBox="0 0 200 200" aria-hidden="true"><circle class="import-ring-track" cx="100" cy="100" r="86"/><circle class="import-ring-fill" cx="100" cy="100" r="86" pathLength="100"/></svg>
                                <div class="import-ring-content"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i><strong class="import-activity-value">0%</strong><span class="import-activity-stage">{{ trans('general.uploading') }}</span></div>
                            </div>
                            <p class="import-activity-help" role="status" aria-live="polite">{{ trans('presentation.import_transfer_help') }}</p>
                            <button type="button" class="btn btn-default btn-block" id="cancel-import-upload">{{ trans('presentation.import_cancel_upload') }}</button>
                        </div>
                        {{-- One progress bar per uploading file. JS
                             appends a `.upload-progress-item` for each
                             file in the fileupload widget's add() callback
                             and mutates its own bar/message in place, so
                             concurrent uploads don't trample each other's
                             UI. wire:ignore keeps Livewire's morphdom
                             from wiping the JS-appended children when
                             uploadSucceeded / uploadFailed triggers a
                             component re-render - especially important
                             for failed uploads whose error text needs to
                             stay put so the user can read it. --}}
                        <div id="upload-progress-list" style="margin-bottom: 15px;" wire:ignore role="status" aria-live="polite"></div>
                        <template id="upload-progress-item-tpl">
                            <div class="upload-progress-item" style="margin-bottom: 10px;">
                                <small class="upload-progress-filename text-muted" style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></small>
                                <div class="progress" style="margin-bottom: 4px;">
                                    <div class="progress-bar progress-bar-warning" role="progressbar" style="width: 0%;">
                                        <span class="sr-only upload-progress-sr">0%</span>
                                    </div>
                                </div>
                                <p class="upload-progress-message" style="margin-bottom: 0;"></p>
                            </div>
                        </template>


                    </div>
                </div>
            </div>

        </div>

    {{-- Import wizard modal. Three steps: pick type + options, map
         columns, preview first N rows. Opened by selectFile() via
         dispatch('open-import-modal'). The submit ("Process") button
         on step 3 is the only path that fires the actual import POST;
         steps 1 and 2 just advance wizardStep. --}}
    <div wire:ignore.self class="modal fade" id="importMappingModal" tabindex="-1" role="dialog" aria-labelledby="importMappingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content" data-motion-wizard-step="{{ $wizardStep }}">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('button.close') }}" @disabled($processing)>
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="importMappingModalLabel">
                        @if ($this->activeFile)
                            {{ $this->activeFile->file_path }}
                        @else
                            {{ trans('general.import') }}
                        @endif
                    </h4>
                    <p class="text-muted" style="margin-top: 6px; margin-bottom: 0;">
                        {{ trans_choice('admin/hardware/message.import.row_count', $activeFileRowCount, ['count' => $activeFileRowCount]) }}
                    </p>
                </div>
                @unless ($processing)
                <nav class="import-stepper form-stepper" aria-label="{{ trans('presentation.upload_stage') }}">
                    <span class="sr-only" role="status" aria-live="polite">{{ trans('presentation.import_step_position', ['current' => $wizardStep, 'total' => 3]) }}</span>
                    <ol class="stepper-list">
                        @foreach ([1 => ['type', 'import_step_type_help'], 2 => ['map', 'import_step_map_help'], 3 => ['preview', 'import_step_preview_help']] as $number => $step)
                            <li class="stepper-item {{ $wizardStep === $number ? 'is-current' : ($wizardStep > $number ? 'is-complete' : '') }}">
                                <button type="button" class="stepper-button" data-import-step="{{ $number }}"
                                        @disabled($number >= $wizardStep)
                                        @if ($wizardStep === $number) aria-current="step" @endif>
                                    <span class="stepper-marker" aria-hidden="true">{{ $wizardStep > $number ? '✓' : $number }}</span>
                                    <span class="stepper-label">{{ trans('admin/hardware/message.import.wizard.step_'.$step[0]) }}</span>
                                    <span class="stepper-hint">{{ trans('presentation.'.$step[1]) }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </nav>
                @endunless
                {{-- form-horizontal on the body so .control-label picks up
                     the right-aligned style bootstrap only applies inside
                     a form-horizontal container. Everything in this modal
                     uses the col-md-3 label / col-md-9 input layout, so
                     this is safe across all three steps. --}}
                <div class="modal-body form-horizontal" tabindex="-1" role="region" aria-label="{{ trans('admin/hardware/message.import.wizard.step_'.([1 => 'type', 2 => 'map', 3 => 'preview'][$wizardStep] ?? 'type')) }}">
                    {{-- bs-wizard progress header. Same class set the
                         setup wizard uses so we inherit the styles
                         already shipped in all.css. Three col-xs-4
                         cells because we have three steps here. Hidden
                         during processing so the small step-indicator
                         bars don't visually compete with the actual
                         processing progress bar rendered below. --}}


                    {{-- Status text (e.g. "required fields not mapped")
                         renders above the step content so users see the
                         message before scrolling past the form to find
                         out why their click didn't advance. --}}
                    @if ($statusText)
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert{{ $statusType == 'success' ? ' alert-success' : ($statusType == 'error' ? ' alert-danger' : ' alert-info') }}" style="margin-bottom: 15px;">
                                    {!! $statusText !!}
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Step 1: type selection + type-specific options. Only
                         step 2's mapping grid gets an x-well; step 1's plain
                         form fields sit directly in the modal body. --}}
                    @if ($wizardStep === 1)
                        <div class="row">
                            <div class="form-group">
                                <label for="typeOfImport" class="col-md-3 col-xs-12 control-label">
                                    {{ trans('general.import_type') }}
                                </label>
                                <div class="col-md-6 col-xs-12">
                                    <x-input.select
                                        name="typeOfImport"
                                        id="import_type"
                                        :options="$importTypes"
                                        :selected="$typeOfImport"
                                        :for-livewire="true"
                                        :include-empty="true"
                                        :data-placeholder="trans('general.select_var', ['thing' => trans('general.import_type')])"
                                        placeholder=""
                                        data-minimum-results-for-search="-1"
                                        style="width: 100%"
                                    />
                                    @if ($typeOfImport === 'asset' && $snipeSettings->auto_increment_assets == 0)
                                        <p class="help-block">
                                            {{ trans('general.auto_incrementing_asset_tags_disabled_so_tags_required') }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Asset history import doesn't create or update
                                 rows in the "entity" sense - it writes
                                 actionlogs and re-stamps the assigned_to
                                 field. "Update existing values" has no
                                 meaning there. --}}
                            @if ($typeOfImport !== 'assetHistory')
                                <x-form.checkbox-row
                                    name="update"
                                    :label="trans('general.update_existing_values')"
                                    :help_text="trans('general.update_mode_help')"
                                    :checked="(bool) $update"
                                    wire:model.live="update"
                                />

                                {{-- Only useful when Update Existing Values
                                     is on. Default to clear-blanks behavior
                                     unless the importing user explicitly opts in to
                                     preserving DB values on blank CSV cells. --}}
                                @if ($update)
                                    <x-form.checkbox-row
                                        name="preserve_blanks"
                                        :label="trans('general.preserve_blank_cells_on_update')"
                                        :help_text="trans('general.preserve_blank_cells_on_update_help')"
                                        :checked="(bool) $preserve_blanks"
                                        wire:model.live="preserve_blanks"
                                    />
                                @endif
                            @endif

                            @if ($typeOfImport === 'asset' && $snipeSettings->auto_increment_assets == 1 && $update)
                                <div class="form-group">
                                    <p class="help-block col-md-8 col-md-offset-3">
                                        {{ trans('general.auto_incrementing_asset_tags_enabled_so_now_assets_will_be_created') }}
                                    </p>
                                </div>
                            @endif

                            @if (($typeOfImport === 'user' || $this->hasUserCheckoutMapping) && $typeOfImport !== 'assetHistory')
                                {{-- Also shown for non-user imports (asset,
                                     accessory, etc.) when the current column
                                     mapping includes any user-identifying
                                     field, since those imports may check
                                     items out to users. The welcome email
                                     only fires for users that are actually
                                     created by the importer; existing-user
                                     matches don't retrigger it.

                                     assetHistory is excluded because it
                                     never creates users --}}
                                <x-form.checkbox-row
                                    name="send_welcome"
                                    :label="trans('general.send_welcome_email_to_users')"
                                    :checked="(bool) $send_welcome"
                                    :help_text="trans('general.send_welcome_email_import_help')"
                                    wire:model.live="send_welcome"
                                />
                            @endif

                            {{-- assetHistory matcher labels ship as
                                 translation strings that include <strong>
                                 and <code> markup. The boxed
                                 x-form.checkbox-row (label.form-control)
                                 doesn't lay these out well - the inline
                                 <strong>/<code> block elements push the
                                 layout around inside a fixed-height box.
                                 Use Bootstrap 3's plain .checkbox wrapper
                                 instead, which handles multi-formatted
                                 labels cleanly. --}}
                            @if ($typeOfImport === 'assetHistory')
                                <div class="form-group">
                                    <div class="col-md-8 col-md-offset-3">
                                        <p class="help-block">{!! trans('admin/hardware/general.import_text') !!}</p>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="col-md-8 col-md-offset-3">
                                        @foreach ([
                                            'match_firstnamelastname' => 'csv_import_match_f-l',
                                            'match_flastname' => 'csv_import_match_initial_last',
                                            'match_firstname' => 'csv_import_match_first',
                                            'match_email' => 'csv_import_match_email',
                                            'match_username' => 'csv_import_match_username',
                                        ] as $prop => $transKey)
                                            <div class="checkbox">
                                                <label>
                                                    <input type="checkbox" wire:model.live="{{ $prop }}" @checked((bool) $$prop)>
                                                    {{-- Extra span for visual spacing between the
                                                         checkbox input and the label text; a plain
                                                         whitespace gap gets collapsed against inline
                                                         HTML (<strong>/<code>) that follows. --}}
                                                    <span style="margin-left: 6px;">{!! trans('admin/hardware/general.'.$transKey) !!}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <x-form.checkbox-row
                                name="run_backup"
                                :label="trans('general.back_before_importing')"
                                :checked="(bool) $run_backup"
                                wire:model.live="run_backup"
                            />
                        </div>
                    @endif

                    {{-- Step 2: column mapping --}}
                    @if (in_array($wizardStep, [2, 3]) && $typeOfImport && $this->activeFile && !$processing)
                        @php
                            $mappingCounts = array_count_values(array_filter((array) $field_map, fn ($value) => is_string($value) && $value !== '' && $value !== '-'));
                            $mappedCount = array_sum($mappingCounts);
                            $missingMappings = $this->missingRequiredMappings();
                            $duplicateTargets = array_keys(array_filter($mappingCounts, fn ($count) => $count > 1));
                        @endphp
                        <div class="import-mapping-summary" aria-live="polite">
                            <span>{{ trans('presentation.import_mapped') }}: <strong>{{ $mappedCount }}</strong></span>
                            <span>{{ trans('presentation.import_ignored') }}: <strong>{{ count($headerRow) - $mappedCount }}</strong></span>
                            <span class="{{ $missingMappings ? 'text-warning' : '' }}">{{ $missingMappings ? trans('presentation.import_missing_required').': '.implode(', ', array_map(fn ($key) => $columnOptions[$typeOfImport][$key] ?? $key, $missingMappings)) : trans('presentation.import_required_complete') }}</span>
                        </div>
                        @if ($duplicateTargets)<p class="alert alert-warning">{{ trans('presentation.import_duplicate_help') }}</p>@endif
                    @endif
                    @if ($wizardStep === 2 && $typeOfImport && $this->activeFile)
                        <div class="row">
                            <div class="col-md-12">
                                <x-well class="import-mapping-panel">
                                    <div class="form-group col-md-12">
                                        <h2 style="margin-top: 0;">
                                            <i class="{{ \App\Helpers\IconHelper::icon($typeOfImport) }}"></i>
                                            {{ trans('general.map_fields', ['item_type' => $importTypes[$typeOfImport]]) }}
                                        </h2>
                                    </div>

                                    @php
                                        $requiredKeys = $this->requiredForType($typeOfImport);
                                    @endphp

                                    <div class="import-mapping-tools" wire:ignore>
                                        <label>{{ trans('presentation.import_search') }}<input type="search" class="form-control" data-import-mapping-search></label>
                                        <label>{{ trans('presentation.import_show') }}<select class="form-control" data-import-mapping-filter>
                                            <option value="all">{{ trans('presentation.import_all_columns') }}</option>
                                            <option value="mapped">{{ trans('presentation.import_mapped') }}</option>
                                            <option value="ignored">{{ trans('presentation.import_ignored') }}</option>
                                            <option value="duplicate">{{ trans('presentation.import_duplicate') }}</option>
                                        </select></label>
                                    </div>
                                    <p data-import-mapping-empty hidden>{{ trans('presentation.import_no_columns') }}</p>
                                    <div class="form-group col-md-12 import-mapping-head">
                                        <div class="col-md-3 text-right">
                                            <strong>{{ trans('general.csv_header_field') }}</strong>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>{{ trans('general.import_field') }}</strong>
                                        </div>
                                        <div class="col-md-5">
                                            <strong>{{ trans('general.sample_value') }}</strong>
                                        </div>
                                    </div>

                                    @if (! empty($headerRow))
                                        @foreach ($headerRow as $index => $header)
                                            @php
                                                // Render every CSV header, whether or
                                                // not the auto-map bound it to a target.
                                                // Auto-unmapped columns come through
                                                // with $currentMapping = null and render
                                                // with the "Do not import" placeholder
                                                // selected; the user can pick a target
                                                // from the dropdown if they want to. An
                                                // earlier iteration of the wizard hid
                                                // unmapped columns to keep the mapping
                                                // step focused, but reporter feedback
                                                // (swift2512 / Dewi4nt on #19450) was
                                                // that people want to see every column
                                                // so they can hand-map anything the
                                                // auto-matcher missed.
                                                $currentMapping = $field_map[$index] ?? null;
                                            @endphp

                                            <div class="form-group col-md-12 import-mapping-row" wire:key="header-row-{{ $index }}" data-mapping-state="{{ !$currentMapping ? 'ignored' : (in_array($currentMapping, $duplicateTargets, true) ? 'duplicate' : 'mapped') }}">
                                                <label for="import-map-{{ $index }}" class="col-md-3 control-label text-right">{{ $header }}<small>{{ !$currentMapping ? trans('presentation.import_ignored') : (in_array($currentMapping, $duplicateTargets, true) ? trans('presentation.import_duplicate') : (in_array($currentMapping, $requiredKeys, true) ? trans('presentation.import_required') : '')) }}</small></label>
                                                <div class="col-md-4">
                                                    {{-- Rows whose current mapping is a required
                                                         target field get the browser :required
                                                         pseudo-class, so the app-wide input:required
                                                         style renders an orange right-border. Same
                                                         visual convention as model-required fields
                                                         elsewhere in the app. --}}
                                                    <x-input.select
                                                        :id="'import-map-'.$index"
                                                        :name="'field_map.'.$index"
                                                        :for-livewire="true"
                                                        :placeholder="trans('general.importer.do_not_import')"
                                                        :required="in_array($currentMapping, $requiredKeys, true)"
                                                        class="mappings"
                                                        style="min-width: 100%;"
                                                    >
                                                        <option selected="selected" value="">{{ trans('general.importer.do_not_import') }}</option>
                                                        @foreach ($columnOptions[$typeOfImport] as $key => $value)
                                                            <option
                                                                value="{{ $key }}"
                                                                @selected($currentMapping === $key)
                                                                @disabled($key === '-')
                                                            >{{ $value }}</option>
                                                        @endforeach
                                                    </x-input.select>
                                                </div>
                                                @if (($this->activeFile->first_row) && (array_key_exists($index, $this->activeFile->first_row)))
                                                    <div class="col-md-5">
                                                        <p class="form-control-static" data-label="{{ trans('general.sample_value') }}" title="{{ $this->activeFile->first_row[$index] }}">{{ str_limit($this->activeFile->first_row[$index], 90, '...') }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        {{ trans('general.no_headers') }}
                                    @endif
                                </x-well>
                            </div>
                        </div>
                    @endif

                    {{-- Step 3: preview + Process (or processing-progress
                         once Process has been clicked). Two bars in the
                         processing state - backup first (only when the
                         user asked for one, since the sync backup runs
                         at the top of Api\ImportController::process()
                         before any row work), then the import progress
                         which fills as slices complete. --}}
                    @if ($wizardStep === 3 && $processing)
                        <div class="row">
                            <div class="col-md-12">
                                @if ($backupRequested)
                                    <p style="margin-bottom: 4px;">
                                        <strong>{{ trans('admin/hardware/message.import.backup_label') }}</strong>
                                    </p>
                                    {{-- Fake progress. The sync backup
                                         emits no percentage we can wire
                                         to a real bar, so JS eases the
                                         width from 0% toward 90% on an
                                         curve while slice 0
                                         is in flight, then the .done()
                                         callback snaps to 100% green.
                                         wire:ignore keeps Livewire
                                         from wiping the JS-driven inline style during
                                         re-renders. --}}
                                    <div wire:ignore class="progress" id="backup-progress-track" style="margin-bottom: 4px; height: 20px;">
                                        <div id="backup-progress-bar" class="progress-bar progress-bar-warning" role="progressbar" style="width: 0%;">
                                            <span class="sr-only">0%</span>
                                        </div>
                                    </div>
                                    <p id="backup-progress-text" style="margin-bottom: 16px;">
                                        @if ($backupComplete)
                                            <i class="fas fa-check text-success" aria-hidden="true"></i>
                                            {{ trans('admin/hardware/message.import.backup_complete') }}
                                        @else
                                            <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                                            {{ trans('admin/hardware/message.import.backup_running') }}
                                        @endif
                                    </p>
                                @endif

                                <div class="import-activity import-process-activity">
                                    <div class="import-activity-ring {{ $progress <= 0 ? 'is-preparing' : '' }}" role="progressbar" aria-label="{{ trans('admin/hardware/message.import.import_label') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ max(0, $progress) }}" style="--import-progress:{{ max(0, $progress) }}">
                                        <svg viewBox="0 0 200 200" aria-hidden="true"><circle class="import-ring-track" cx="100" cy="100" r="86"/><circle class="import-ring-fill" cx="100" cy="100" r="86" pathLength="100"/></svg>
                                        <div class="import-ring-content"><i class="fas fa-file-import" aria-hidden="true"></i><strong>{{ max(0, $progress) }}%</strong><span>{{ trans('admin/hardware/message.import.import_label') }}</span></div>
                                    </div>
                                    <p class="import-activity-help">{{ trans('presentation.import_stop_help') }}</p>
                                    <button type="button" class="btn btn-default" id="cancel-data-import" wire:ignore>{{ trans('presentation.import_cancel_import') }}</button>
                                </div>
                                @if (! empty($progress_message))
                                    <p id="process-progress-text">{!! $progress_message !!}</p>
                                @endif
                            </div>
                        </div>
                    @elseif ($wizardStep === 3)
                        @php
                            // Preview only shows columns the user chose to
                            // import - "Do not import" columns are dropped
                            // entirely so the table stays scannable on wide
                            // CSVs. Build the visible column set once here
                            // and reuse it for both headers and body cells.
                            $visibleColumns = [];
                            foreach ($headerRow as $index => $header) {
                                $mappedKey = $field_map[$index] ?? null;
                                if (! $mappedKey) {
                                    continue;
                                }
                                $visibleColumns[] = [
                                    'header' => $header,
                                    'mappedLabel' => $columnOptions[$typeOfImport][$mappedKey] ?? $mappedKey,
                                ];
                            }
                        @endphp

                        <div class="row">
                            <div class="col-md-12">
                                <div class="import-review-options">
                                    <span>{{ $importTypes[$typeOfImport] }}</span>
                                    @if ($typeOfImport !== 'assetHistory')
                                        <span>{{ trans('general.update_existing_values') }}: {{ $update ? trans('general.yes') : trans('general.no') }}</span>
                                        @if ($update)<span>{{ trans('general.preserve_blank_cells_on_update') }}: {{ $preserve_blanks ? trans('general.yes') : trans('general.no') }}</span>@endif
                                        @if ($typeOfImport === 'user' || $this->hasUserCheckoutMapping)<span>{{ trans('general.send_welcome_email_to_users') }}: {{ $send_welcome ? trans('general.yes') : trans('general.no') }}</span>@endif
                                    @endif
                                    <span>{{ trans('presentation.import_backup') }}: {{ $run_backup ? trans('general.yes') : trans('general.no') }}</span>
                                </div>
                                <p class="help-block">{{ trans('presentation.import_preview_help') }}</p>
                                <p>{{ trans('admin/hardware/message.import.wizard.preview_intro', ['count' => count($previewRows)]) }}</p>
                                @if (! empty($previewRows) && ! empty($visibleColumns))
                                    {{-- Sticky first column so users can
                                         scroll horizontally through wide
                                         imports without losing the row's
                                         identity. Scoped to
                                         .preview-sticky-first via inline
                                         style below so nothing else in
                                         the app is affected. Background
                                         uses currentColor's inverse via
                                         the theme's own body-bg CSS var
                                         so it works in light + dark
                                         mode; the box-shadow gives a
                                         subtle right-edge divider. --}}
                                    <style>
                                        #importMappingModal .preview-sticky-first thead th:first-child,
                                        #importMappingModal .preview-sticky-first tbody td:first-child {
                                            position: sticky;
                                            left: 0;
                                            z-index: 2;
                                            background-color: var(--section-bg, #fff);
                                            box-shadow: 2px 0 2px -1px rgba(0, 0, 0, 0.1);
                                        }

                                        [data-theme="dark"] #importMappingModal .preview-sticky-first thead th:first-child,
                                        [data-theme="dark"] #importMappingModal .preview-sticky-first tbody td:first-child {
                                            background-color: #2a2a2a;
                                            box-shadow: 2px 0 2px -1px rgba(255, 255, 255, 0.08);
                                        }
                                    </style>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-condensed preview-sticky-first">
                                            <thead>
                                                <tr>
                                                    @foreach ($visibleColumns as $col)
                                                        {{-- Just the target field name in the
                                                             header; the CSV column-to-target
                                                             mapping was already confirmed in
                                                             step 2 so we don't need to repeat
                                                             both here. Full CSV column name
                                                             surfaces in the title tooltip. --}}
                                                        <th scope="col" title="{{ $col['header'] }}">{{ $col['mappedLabel'] }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($previewRows as $row)
                                                    <tr>
                                                        @foreach ($visibleColumns as $col)
                                                            @php
                                                                $cellValue = (string) ($row[$col['header']] ?? '');
                                                            @endphp
                                                            <td title="{{ $cellValue }}">{{ str_limit($cellValue, 25, '...') }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Belt-and-suspenders clearfix so any stray float
                         in the step content doesn't push the modal-footer
                         around. --}}
                    <div class="clearfix"></div>
                </div>
                <div class="modal-footer">
                    {{-- While processing all footer buttons hide - the
                         slice loop shouldn't be interruptible mid-flight
                         or we'd leave the import half-committed. --}}
                    @unless ($processing)
                        <span class="import-not-started">{{ trans('presentation.import_not_started') }}</span>
                        @if ($wizardStep > 1)
                            <button type="button" class="btn btn-default pull-left" wire:click="previousStep" wire:loading.attr="disabled" wire:target="previousStep,nextStep">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                                {{ trans('admin/hardware/message.import.wizard.back') }}
                            </button>
                        @else
                            {{-- Cancel only makes sense on step 1; past that,
                                 "Back" is the way to unwind. Users can still
                                 close via the modal's × or backdrop click. --}}
                            <a href="#" class="pull-left" style="padding: 6px 12px; margin-left: 10px;" data-dismiss="modal">{{ trans('general.cancel') }}</a>
                        @endif

                        @if ($wizardStep === 1)
                            <button type="button" class="btn btn-primary" wire:click="nextStep" wire:loading.attr="disabled" wire:target="previousStep,nextStep" @disabled(! $typeOfImport)>
                                {{ trans('admin/hardware/message.import.wizard.next') }}
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        @elseif ($wizardStep === 2)
                            <button type="button" class="btn btn-primary" wire:click="nextStep" wire:loading.attr="disabled" wire:target="previousStep,nextStep">
                                {{ trans('admin/hardware/message.import.wizard.preview_button') }}
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        @else
                            {{-- Demo mode: server side blocks the actual
                                 API POST + Livewire startProcessing(); we
                                 disable the button here so the user gets
                                 an obvious signal instead of clicking and
                                 seeing a 422. --}}
                            <button type="button" class="btn btn-success" id="import" @disabled(config('app.lock_passwords'))>
                                <i class="fas fa-check" aria-hidden="true"></i>
                                {{ trans('presentation.import_start') }}
                            </button>
                        @endif
                    @endunless
                </div>
            </div>
            </div>
        </div>

        {{-- Bulk delete confirmation modal. Kept as a Livewire-driven Bootstrap
             modal (not the shared confirm-action partial, which POSTs a form)
             so the confirm button can fire wire:click and let Livewire handle
             the delete without a page reload. --}}
        <div wire:ignore.self class="modal fade" id="bulkDeleteImportsModal" tabindex="-1" role="dialog" aria-labelledby="bulkDeleteImportsModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('button.cancel') }}">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="bulkDeleteImportsModalLabel">
                            {{ trans('admin/hardware/message.import.bulk_delete.confirm_title') }}
                        </h4>
                    </div>
                    <div class="modal-body">
                        <p>
                            {{ trans('admin/hardware/message.import.bulk_delete.confirm_body', ['count' => count($selectedIds)]) }}
                        </p>
                        @if (count($selectedIds) > 0)
                            <ul>
                                @foreach ($this->files as $currentFile)
                                    @if (in_array((string) $currentFile->id, $selectedIds, true) || in_array($currentFile->id, $selectedIds, true))
                                        <li>{{ $currentFile->file_path }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <a href="#" class="pull-left" data-dismiss="modal">{{ trans('button.cancel') }}</a>
                        <button type="button" class="btn btn-danger" wire:click="bulkDestroy" data-dismiss="modal">
                            {{ trans('admin/hardware/message.import.bulk_delete.confirm_button') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
</div>
@script
    <script>

        {{-- Per-file progress. With multiple uploads in flight the single
             shared $progress prop from before would trample itself. Each
             file gets its own DOM element cloned from #upload-progress-
             item-tpl. Keyed by the File object itself (via Map) because
             jQuery fileupload creates fresh `data` context objects for
             each event (add/progress/done/fail), so stashing a reference
             on data.* only survives one event and we'd end up rendering
             a duplicate row per file. --}}
        var uploadItems = new Map();
        var uploadTransfers = new Map();
        function updateUploadActivity() {
            var transfers = Array.from(uploadTransfers.values());
            var pending = transfers.filter(function (item) { return !item.finished; });
            var $activity = $('#import-upload-activity');
            var total = transfers.reduce(function (sum, item) { return sum + item.total; }, 0);
            var loaded = transfers.reduce(function (sum, item) { return sum + item.loaded; }, 0);
            var pct = total ? Math.min(100, Math.floor(loaded / total * 100)) : 0;
            var preparing = pending.length && pending.every(function (item) { return item.loaded >= item.total; });
            $activity.prop('hidden', !pending.length);
            $('.import-upload-panel').toggleClass('is-uploading', pending.length > 0);
            $activity.find('.import-activity-ring').css('--import-progress', pct).toggleClass('is-preparing', !!preparing || (pending.length > 0 && pending.every(function (item) { return item.loaded === 0; }))).attr('aria-valuenow', pct);
            $activity.find('.import-activity-value').text(preparing ? @json(trans('presentation.import_preparing')) : pct + '%');
            $activity.find('.import-activity-stage').text(preparing ? @json(trans('presentation.import_checking')) : @json(trans('general.uploading')));
            $activity.find('.import-activity-help').text(@json(trans('presentation.import_transfer_help')));
        }
        $(document).on('click', '#cancel-import-upload', function () {
            uploadTransfers.forEach(function (item) {
                if (item.finished) return;
                item.cancelled = true; item.finished = true;
                if (item.request) item.request.abort();
                uploadError(item.data, @json(trans('presentation.import_upload_cancelled')));
            });
            updateUploadActivity();
        });

        function uploadItemFor(data) {
            var file = data.files && data.files[0];
            if (!file) return null;
            if (uploadItems.has(file)) return uploadItems.get(file);
            var $item = $($('#upload-progress-item-tpl').prop('content')).find('.upload-progress-item').first().clone();
            $item.find('.upload-progress-filename').text(file.name || '').attr('title', file.name || '');
            $('#upload-progress-list').append($item);
            uploadItems.set(file, $item);
            return $item;
        }

        var importUploadLimit = @json(Helper::file_upload_max_size());
        function uploadError(data, message) {
            var $item = uploadItemFor(data);
            if (!$item) return;
            $item.find('.progress-bar').removeClass('progress-bar-warning progress-bar-success').addClass('progress-bar-danger').css('width', '100%').attr('aria-valuenow', 0);
            $item.find('.upload-progress-message').addClass('text-danger').text(message);
            $item.find('.upload-progress-sr').text(message);
        }
        $('#import-upload-zone').on('dragover', function () { $(this).addClass('is-dragging'); })
            .on('dragleave drop', function () { $(this).removeClass('is-dragging'); });
        $(document).on('dragover.importUpload drop.importUpload', function (event) {
            var transfer = event.originalEvent && event.originalEvent.dataTransfer;
            if (transfer && Array.from(transfer.types || []).indexOf('Files') !== -1) event.preventDefault();
        });
        $('#fileupload').fileupload({
            dropZone: $('#import-upload-zone'),
            dataType: 'json',
            singleFileUploads: true,
            change: function (e, data) {
                // Fires once per user file-picker action, before add()
                // splits the batch into per-file entries. Wipe the prior
                // batch's rows so success/failure UI from an earlier
                // attempt doesn't linger alongside the new one.
                $('#upload-progress-list').empty();
                uploadItems.clear();
                uploadTransfers.clear();
            },
            drop: function (e, data) {
                // Same clear on drag-drop uploads.
                $('#upload-progress-list').empty();
                uploadItems.clear();
                uploadTransfers.clear();
            },
            add: function(e, data) {
                data.headers = {
                    "X-Requested-With": 'XMLHttpRequest',
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                };
                uploadItemFor(data);
                var file = data.files && data.files[0];
                var allowedMime = ['application/vnd.ms-excel', 'text/csv', 'application/csv', 'text/x-Algol68', 'text/plain', 'text/comma-separated-values', 'text/tsv'];
                if (!file || (!/\.(csv|tsv|txt)$/i.test(file.name || '') && allowedMime.indexOf(file.type) === -1)) {
                    uploadError(data, @json(trans('presentation.import_invalid_file')));
                    return;
                }
                if (importUploadLimit > 0 && file.size > importUploadLimit) {
                    uploadError(data, @json(trans('presentation.import_too_large')));
                    return;
                }
                var transfer = {data: data, loaded: 0, total: file.size || 1, finished: false, cancelled: false, request: null};
                uploadTransfers.set(file, transfer); updateUploadActivity();
                data.process().done(function () {
                    if (!transfer.cancelled) transfer.request = data.submit();
                }).fail(function () {
                    transfer.finished = true; updateUploadActivity();
                    uploadError(data, @json(trans('general.upload_error')));
                });
                $wire.clearMessage();
            },
            progress: function(e, data) {
                var transfer = uploadTransfers.get(data.files[0]);
                if (transfer && !transfer.cancelled) { transfer.loaded = data.loaded; transfer.total = data.total; updateUploadActivity(); }
                var $item = uploadItemFor(data);
                var pct = Math.round((data.loaded / data.total) * 100);
                $item.find('.progress-bar').css('width', pct + '%').attr({'aria-valuenow': pct, 'aria-valuemin': 0, 'aria-valuemax': 100});
                $item.find('.upload-progress-sr').text(pct + '%');
                $item.find('.upload-progress-message').removeClass('text-danger').text(pct === 100 ? @json(trans('presentation.import_checking')) : @json(trans('general.uploading')));
            },
            done: function (e, data) {
                var transfer = uploadTransfers.get(data.files[0]);
                if (transfer) { transfer.loaded = transfer.total; transfer.finished = true; updateUploadActivity(); }
                // Success: mark THIS file's bar green + clear its
                // "Uploading..." caption. Also pass the returned IDs so
                // Livewire can highlight the new file's row in the list.
                var $item = uploadItemFor(data);
                $item.find('.progress-bar').removeClass('progress-bar-warning progress-bar-danger').addClass('progress-bar-success').css('width', '100%');
                $item.find('.upload-progress-sr').text('100%');
                $item.find('.upload-progress-message').text(@json(trans('presentation.import_uploaded')));

                var uploadedIds = (data.result && data.result.files)
                    ? data.result.files.map(function (f) {
                        return f.id;
                    })
                    : [];
                $wire.uploadSucceeded(uploadedIds);
            },
            fail: function (e, data) {
                var transfer = uploadTransfers.get(data.files[0]);
                if (transfer) { transfer.finished = true; updateUploadActivity(); }
                if (transfer && transfer.cancelled) return;
                var response = data.jqXHR && data.jqXHR.responseJSON;
                var message = response && (response.message || response.messages);
                if (message && typeof message === 'object') message = Object.values(message).flat().join(' ');
                uploadError(data, typeof message === 'string' && message ? message : @json(trans('general.upload_error')));
                $wire.uploadFailed();
            },
        });

        // Highlight-fade choreography for the freshly-uploaded rows:
        //   T+0     spinning star appears (Livewire render after upload)
        //   T+2000  add opacity:0 - CSS transition on the star handles
        //           the visual dissolve over the next 600ms
        //   T+2600  ask Livewire to clear the tracked IDs so the star
        //           element leaves the DOM on the next render
        window.addEventListener('new-uploads-highlighted', function () {
            setTimeout(function () {
                $('.newly-uploaded-star').css('opacity', '0');
                setTimeout(function () {
                    $wire.clearNewlyUploadedIds();
                }, 600);
            }, 2000);
        });

        var returningImportStep = false;
        $(document).on('click', '#importMappingModal [data-import-step]', async function () {
            if (returningImportStep) return;
            var target = Number(this.dataset.importStep);
            var current = Number($wire.$get('wizardStep'));
            if (target >= current || $wire.$get('processing')) return;
            returningImportStep = true;
            $('#importMappingModal [data-import-step]').prop('disabled', true);
            try {
                while (current > target) { await $wire.previousStep(); current = Number($wire.$get('wizardStep')); }
            } finally {
                returningImportStep = false;
                $('#importMappingModal [data-import-step]').each(function () { this.disabled = Number(this.dataset.importStep) >= current; });
            }
        });
        function filterImportMappings() {
            var modal = document.getElementById('importMappingModal');
            var input = modal.querySelector('[data-import-mapping-search]');
            var filter = modal.querySelector('[data-import-mapping-filter]');
            if (!input || !filter) return;
            var query = input.value.toLowerCase().trim(), shown = 0;
            modal.querySelectorAll('.import-mapping-row').forEach(function (row) {
                var matches = row.querySelector('label').textContent.toLowerCase().includes(query);
                matches = matches && (filter.value === 'all' || row.dataset.mappingState === filter.value || (filter.value === 'mapped' && row.dataset.mappingState === 'duplicate'));
                row.hidden = !matches; if (matches) shown++;
            });
            var empty = modal.querySelector('[data-import-mapping-empty]'); if (empty) empty.hidden = shown !== 0;
        }
        $(document).on('input change', '#importMappingModal [data-import-mapping-search], #importMappingModal [data-import-mapping-filter]', filterImportMappings);
        new MutationObserver(function (records) {
            filterImportMappings();
            if (records.some(function (record) { return record.attributeName === 'data-motion-wizard-step'; })) requestAnimationFrame(function () {
                var modal = document.getElementById('importMappingModal');
                if (!modal.classList.contains('in')) return;
                var body = modal.querySelector('.modal-body');
                body.scrollTop = 0; body.focus({preventScroll: true});
            });
        }).observe(document.getElementById('importMappingModal'), {subtree: true, childList: true, attributes: true, attributeFilter: ['data-mapping-state', 'data-motion-wizard-step']});

        // Open the mapping modal when the Livewire component asks us to.
        // Fires from selectFile() after headerRow / field_map / row count
        // are populated so the modal has real content on first paint.
        window.addEventListener('open-import-modal', function () {
            $('#importMappingModal').modal('show');
        });

        // select2 measures its parent width at init time. Because
        // snipeit.js runs select2 init on all .select2 elements on
        // page-ready, the type dropdown inside the modal gets a 0-width
        // parent (modal is display:none) and renders as a tiny stub. Kick
        // it after Bootstrap 3's shown.bs.modal fires so it can measure
        // for real. Full-width to match the modal-body column, matching
        // what the inline style already asks for via min-width.
        // snipeit.js runs select2() on page-ready with no options, so it
        // measures against the still-hidden modal (0px parent width) and
        // sets .select2-container to width:0px inline. That stays wrong
        // after the modal opens - and worse, it never reflows on browser
        // resize. Re-init on shown.bs.modal with width:'100%' so select2
        // writes a percentage width to the container instead of a pixel
        // value; from there CSS reflow handles resizing for free.
        // dropdownParent scopes the dropdown panel to the modal so it
        // z-indexes correctly above the backdrop.
        $('#importMappingModal').on('shown.bs.modal', function () {
            $('#importMappingModal select.select2').each(function () {
                var $el = $(this);
                if ($el.hasClass('select2-hidden-accessible')) {
                    var current = $el.val();
                    $el.select2('destroy');
                    $el.select2({
                        width: '100%',
                        dropdownParent: $('#importMappingModal'),
                    });
                    if (current !== null) {
                        $el.val(current).trigger('change.select2');
                    }
                }
            });
        });

        var allowImportClose = false, initialImportDraft = null;
        function importDraft() {
            return JSON.stringify({step: $wire.$get('wizardStep'), mapping: $wire.$get('field_map'), fields: Array.from(document.querySelectorAll('#importMappingModal input[name], #importMappingModal select[name]')).map(function (field) {
                return [field.name, field.type === 'checkbox' || field.type === 'radio' ? [field.value, field.checked] : field.value];
            })});
        }
        $('#importMappingModal').on('shown.bs.modal', function () { allowImportClose = false; initialImportDraft = importDraft(); });
        $('#importMappingModal').on('hide.bs.modal', function (event) {
            if (allowImportClose) return;
            if ($wire.$get('processing') || $('#importMappingModal').data('import-running')) { event.preventDefault(); return; }
            if (initialImportDraft && initialImportDraft !== importDraft()) {
                event.preventDefault();
                if (window.InterfaceForms) window.InterfaceForms.confirmDiscard(function () { allowImportClose = true; $('#importMappingModal').modal('hide'); }, document.querySelector('#importMappingModal .close'));
            }
        });

        // Bootstrap 3 fires this after the modal fully closes (backdrop
        // click, ESC, cancel button, close 'x'). Clear activeFileId so
        // Livewire's mapping / row-count state gets reset for the next
        // file. Skip the reset if the modal is closing because we already
        // succeeded (statusType='success' triggers a page redirect anyway).
        $('#importMappingModal').on('hidden.bs.modal', function () {
            if ($wire.$get('statusType') !== 'success') {
                $wire.$set('activeFileId', null);
            }
        });

        // For the importFile part:
        $(function () {

            // Client-side re-entry guard for the Process button. The server
            // holds the actual per-import mutex (see the acquire/release
            // block in Api\ImportController::process), which is what closes
            // the concurrent-writer race for real; this flag just prevents
            // the same-tab wizard from firing a second startProcessing while
            // the first slice chain is still running. Cheap UX polish so
            // the user isn't left wondering whether their impatient
            // second-click did something.
            var isProcessingImport = false, cancelImportRequested = false;
            $(document).on('click', '#cancel-data-import', function () {
                if (!isProcessingImport) return;
                cancelImportRequested = true;
                $(this).prop('disabled', true).text(@json(trans('presentation.import_stopping')));
                $('#process-progress-text').text(@json(trans('presentation.import_stopping_help')));
            });

            // The #import button lives inside #importMappingModal now, but
            // the modal is rendered as a sibling of #upload-table (not
            // inside it), so delegate from document to catch the click
            // regardless of where in the DOM the modal ends up after
            // Bootstrap moves it.
            $(document).on('click', '#importMappingModal #import', function () {
                if (isProcessingImport) {
                    return false;
                }
                if (!$wire.$get('typeOfImport')) {
                    $wire.$set('statusType', 'error');
                    $wire.$set('statusText', "An import type is required... "); //TODO: translate?
                    return;
                }
                isProcessingImport = true;
                cancelImportRequested = false;
                $('#importMappingModal').data('import-running', true);
                $(this).prop('disabled', true).attr('aria-busy', 'true');
                $wire.$set('statusType', 'pending');
                $wire.$set('statusText', '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> {{ trans('admin/hardware/form.processing_spinner') }}');

                // Flip step 3 from preview mode to processing mode so
                // the progress bar takes over that section of the modal
                // and the footer buttons disappear (the footer wraps its
                // buttons in a processing guard). Pass the backup flag
                // so the panel decides whether to render the dedicated
                // backup bar above the import bar.
                var withBackup = !!$wire.$get('run_backup');
                $wire.startProcessing(withBackup);

                // Fake backup progress. Real progress isn't available -
                // the sync artisan snipeit:backup call emits no percentage
                // hook - so tick the bar toward 90% on an asymptotic
                // curve so it visibly moves without ever claiming to be
                // done. The .done()/.fail() for slice 0 snaps it to 100%
                // green when the request actually returns. The Livewire
                // panel gives the bar wire:ignore so this JS-driven
                // width survives re-renders.
                var backupProgressTimer = null;

                function startFakeBackupProgress() {
                    if (!withBackup || !isProcessingImport || cancelImportRequested) return;
                    var $bar = $('#backup-progress-bar');
                    if (!$bar.length) {
                        // Livewire hasn't rendered the panel yet - retry
                        // on next tick.
                        setTimeout(startFakeBackupProgress, 50);
                        return;
                    }
                    var startedAt = Date.now();
                    // tau tuned so bar reaches ~50% around 15s, ~75%
                    // around 30s, ~90% around 60s.
                    var tau = 22000;
                    backupProgressTimer = setInterval(function () {
                        var elapsed = Date.now() - startedAt;
                        // 90 * (1 - e^(-t/tau)) — asymptotes at 90%.
                        var pct = 90 * (1 - Math.exp(-elapsed / tau));
                        $('#backup-progress-bar').css('width', pct.toFixed(1) + '%').find('.sr-only').text(Math.round(pct) + '%');
                    }, 300);
                }

                function finishFakeBackupProgress() {
                    if (backupProgressTimer !== null) {
                        clearInterval(backupProgressTimer);
                        backupProgressTimer = null;
                    }
                    $('#backup-progress-bar').removeClass('progress-bar-warning').addClass('progress-bar-success').css('width', '100%').find('.sr-only').text('100%');
                }

                startFakeBackupProgress();

                // Slice size: how many CSV rows to hand the server per
                // request. Small enough that each slice comfortably fits
                // inside PHP's request budget + any upstream proxy timeout,
                // large enough that the round-trip overhead doesn't
                // dominate. 500 is a compromise; adjust if imports at your
                // scale start bumping the ceiling either way.
                var SLICE_SIZE = {{ (int) config('importer.slice_size', 500) }};

                $wire.generate_field_map().then(function (mappings_raw) {
                    var mappings = JSON.parse(mappings_raw);
                    var file_id = $wire.$get('activeFileId');
                    var totalRows = $wire.$get('activeFileRowCount') || 0;

                    // No rows at all? Nothing to slice, but still hit the
                    // endpoint once so the server-side flash / redirect
                    // logic fires and the user gets consistent feedback.
                    var totalSlices = Math.max(1, Math.ceil(totalRows / SLICE_SIZE));

                    // Aggregated across every slice so a partial-success
                    // failure at slice K doesn't blow away errors from
                    // earlier slices when the modal renders results.
                    var aggregatedErrors = {};
                    var lastRedirectUrl = null;
                    var anySliceFailed = false;
                    var aggregatedTally = {created: 0, updated: 0, skipped: 0, errored: 0};

                    function addTally(tally) {
                        if (!tally) return;
                        aggregatedTally.created += tally.created || 0;
                        aggregatedTally.updated += tally.updated || 0;
                        aggregatedTally.skipped += tally.skipped || 0;
                        aggregatedTally.errored += tally.errored || 0;
                    }

                    function tallySummaryHtml() {
                        return '{{ trans('admin/hardware/message.import.summary.created') }}: <strong>' + aggregatedTally.created + '</strong>' +
                            ' | {{ trans('admin/hardware/message.import.summary.updated') }}: <strong>' + aggregatedTally.updated + '</strong>' +
                            ' | {{ trans('admin/hardware/message.import.summary.skipped') }}: <strong>' + aggregatedTally.skipped + '</strong>' +
                            ' | {{ trans('admin/hardware/message.import.summary.errored') }}: <strong>' + aggregatedTally.errored + '</strong>';
                    }

                    function processSlice(sliceIndex) {
                        if (cancelImportRequested) return $.Deferred().resolve().promise();
                        var offset = sliceIndex * SLICE_SIZE;
                        var isLastSlice = (sliceIndex === totalSlices - 1);
                        var payload = {
                            'import-update': !!$wire.$get('update'),
                            'import-preserve-blanks': !!$wire.$get('preserve_blanks'),
                            'send-welcome': !!$wire.$get('send_welcome'),
                            'import-type': $wire.$get('typeOfImport'),
                            // run-backup only makes sense before the first
                            // slice; downstream slices would trigger a
                            // duplicate backup for no gain.
                            'run-backup': sliceIndex === 0 ? !!$wire.$get('run_backup') : false,
                            'match_username': !!$wire.$get('match_username'),
                            'match_email': !!$wire.$get('match_email'),
                            'match_firstnamelastname': !!$wire.$get('match_firstnamelastname'),
                            'match_flastname': !!$wire.$get('match_flastname'),
                            'match_firstname': !!$wire.$get('match_firstname'),
                            'column-mappings': mappings,
                            'offset': offset,
                            'limit': SLICE_SIZE,
                        };

                        var pctBefore = Math.floor((sliceIndex / totalSlices) * 100);
                        $wire.$set('progress', pctBefore);
                        $wire.$set('progress_bar_class', 'progress-bar-warning');

                        // Slice 0 with run-backup=true is doing the sync
                        // backup for most of its wall-clock time - no
                        // rows are actually landing yet. Clear the
                        // import-side message so the panel isn't
                        // redundant with the dedicated backup bar above;
                        // the import bar just sits at 0% until backup
                        // ends and slice 1 kicks in.
                        if (sliceIndex === 0 && payload['run-backup']) {
                            $wire.$set('progress_message', '');
                        }
                        else {
                            $wire.$set('progress_message',
                                '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ' +
                                'Processing rows ' + (offset + 1) + ' – ' +
                                Math.min(offset + SLICE_SIZE, totalRows) + ' of ' + totalRows + '…',
                            );
                        }

                        return $.post({
                            url: "api/v1/imports/process/" + file_id,
                            contentType: 'application/json',
                            data: JSON.stringify(payload),
                            headers: {
                                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content'),
                            },
                        }).done(function (body) {
                            $wire.$set('progress', Math.floor(((sliceIndex + 1) / totalSlices) * 100));
                            if (body && body.messages && body.messages.redirect_url) {
                                lastRedirectUrl = body.messages.redirect_url;
                            }
                            if (body && body.payload && body.payload.tally) {
                                addTally(body.payload.tally);
                            }
                            // Slice 0's response is our earliest signal
                            // that the sync backup has finished on the
                            // server (it runs at the top of process()
                            // before any row work). Snap the JS-driven
                            // fake progress to 100% green and update
                            // Livewire state so the text below the bar
                            // flips to "Backup complete".
                            if (sliceIndex === 0 && payload['run-backup']) {
                                finishFakeBackupProgress();
                                $wire.markBackupComplete();
                            }
                        }).fail(function (jqXHR) {
                            anySliceFailed = true;
                            // Slice 0 has returned (with an error) - the
                            // backup either finished or was never
                            // attempted. Either way we shouldn't leave
                            // the fake-progress timer running.
                            if (sliceIndex === 0 && payload['run-backup']) {
                                finishFakeBackupProgress();
                                $wire.markBackupComplete();
                            }
                            var body = jqXHR.responseJSON;
                            if (body && body.payload && body.payload.tally) {
                                addTally(body.payload.tally);
                            }
                            if (body && body.status === 'import-errors' && body.messages) {
                                // Merge each slice's per-row messages flat
                                // into the aggregate map. The server
                                // returns { itemIdentity: { tableLabel:
                                // { field: [messages] } } } which the
                                // blade's three-deep foreach + implode
                                // expects. Wrapping under a slice-N key
                                // would add a fourth level and break the
                                // implode.
                                Object.assign(aggregatedErrors, body.messages);
                            }
                            else if (body && body.messages) {
                                // Non-import-errors server error - wrap as
                                // a synthetic row so it still renders in
                                // the errors table under a recognizable
                                // identity.
                                aggregatedErrors['Slice ' + (sliceIndex + 1)] = {
                                    'Server error': {error: [body.messages]},
                                };
                            }
                        });
                    }

                    // Chain slices sequentially. jQuery's .then() returns a
                    // new promise so each slice waits for the previous to
                    // complete (whether success or failure) before firing.
                    var chain = $.Deferred().resolve();
                    for (var i = 0; i < totalSlices; i++) {
                        (function (sliceIndex) {
                            chain = chain.then(function () {
                                return processSlice(sliceIndex);
                            }, function () {
                                // Previous slice failed; keep going. Per-
                                // slice rollback means each slice is
                                // independent, so a partial success is a
                                // valid outcome.
                                return processSlice(sliceIndex);
                            });
                        })(i);
                    }

                    chain.always(function () {
                        // Release the client-side re-entry guard so the
                        // Process button becomes clickable again if the
                        // user needs to retry (e.g. anySliceFailed branch
                        // below keeps them on the wizard). On success the
                        // modal hides and the page redirects anyway, so
                        // the button state is moot in that case.
                        isProcessingImport = false;
                        $('#importMappingModal').data('import-running', false);
                        $('#importMappingModal #import').prop('disabled', false).removeAttr('aria-busy');

                        if (cancelImportRequested) {
                            if (backupProgressTimer !== null) clearInterval(backupProgressTimer);
                            $wire.$set('statusType', 'warning');
                            $wire.$set('statusText', @json(trans('presentation.import_stopped')) + '<br>' + tallySummaryHtml());
                            $wire.stopProcessing();
                            allowImportClose = true;
                            return;
                        }
                        $wire.$set('progress', 100);
                        var somethingLanded = aggregatedTally.created > 0 || aggregatedTally.updated > 0;

                        if (anySliceFailed) {
                            $wire.$set('progress_bar_class', 'progress-bar-danger');
                            $wire.$dispatch('importError', aggregatedErrors);
                            $wire.$set('import_errors', aggregatedErrors);
                            $wire.$set('statusType', 'error');
                            $wire.$set('statusText', "Some slices failed. Successful slices were still committed.<br>" + tallySummaryHtml());
                            // Reset processing so the modal footer's Back
                            // button reappears and the user can retry or
                            // navigate away rather than being trapped in
                            // the progress-bar view.
                            $wire.stopProcessing();
                            $wire.$set('activeFileId', null);
                            allowImportClose = true;
                            $('#importMappingModal').modal('hide');
                            return;
                        }

                        $wire.$set('progress_bar_class', 'progress-bar-success');

                        // If nothing was created or updated (every row was skipped
                        // as a duplicate, typically), stop and surface the summary
                        // instead of redirecting - a silent 0-row-import looks
                        // broken but is usually the user re-importing a file whose
                        // rows already landed the first time.
                        if (!somethingLanded) {
                            $wire.$set('statusType', 'error');
                            $wire.$set('statusText',
                                '{{ trans('admin/hardware/message.import.summary.no_changes') }}<br>' + tallySummaryHtml()
                            );
                            $wire.stopProcessing();
                            $wire.$set('activeFileId', null);
                            allowImportClose = true;
                            $('#importMappingModal').modal('hide');
                            return;
                        }

                        // Rows landed - show the summary briefly, then redirect.
                        // 2500ms is enough to read the counts without being
                        // obnoxious. Longer than the previous 800ms because the
                        // summary is now user-relevant, not decorative.
                        $wire.$set('statusType', 'success');
                        $wire.$set('statusText', tallySummaryHtml());
                        if (lastRedirectUrl) {
                            setTimeout(function () {
                                window.location.href = lastRedirectUrl;
                            }, 2500);
                        }
                    });
                });
                return false;
            });})

    </script>
@endscript
