@extends('layouts/default')
@section('title')
    {{ trans('admin/hardware/general.bulk_checkout') }}
    @parent
@stop
@section('content')
@php
    $bulkCopy = trans('bulk_checkout');
@endphp
<div class="bulk-session" id="bulk-session" data-copy="{{ json_encode($bulkCopy) }}" data-catalog="{{ route('hardware.bulkcheckout.catalog') }}" data-context="{{ route('hardware.bulkcheckout.context') }}" data-submit="{{ route('hardware.bulkcheckout.session') }}">
    <p class="bulk-intro">{{ trans('bulk_checkout.intro') }}</p>
    <form id="bulk-checkout-session-form" method="POST" action="{{ route('hardware.bulkcheckout.session') }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <section class="box box-default bulk-recipient-card" aria-labelledby="bulk-recipient-heading">
            <div class="box-body" id="bulk-recipient-panel">
                <div class="bulk-section-title"><h2 id="bulk-recipient-heading">{{ trans('bulk_checkout.headings.user') }}</h2></div>
                <p class="help-block" id="bulk-mode-hint">{{ trans('bulk_checkout.hints.user') }}</p>
                <div class="bulk-fields">
                    <x-form.row class="bulk-type-field" name="recipient_type" id="bulk-recipient-type" :required="true" :label="trans('bulk_checkout.recipient_type')">
                        <x-slot:input><select id="bulk-recipient-type" class="form-control" required>
                            @foreach(['user', 'location', 'project'] as $recipientMode)
                                @if($recipientMode !== 'project' || auth()->user()->can('update', \App\Models\Project::class))
                                    <option value="{{ $recipientMode }}">{{ trans('bulk_checkout.modes.'.$recipientMode) }}</option>
                                @endif
                            @endforeach
                        </select></x-slot:input>
                    </x-form.row>
                    <div class="bulk-target" data-target="user">
                        <x-input.user-select :label="trans('general.user')" name="assigned_user" :required="true" :hide-new-button="true" />
                    </div>
                    <div class="bulk-target" data-target="location" hidden>
                        <x-form.row name="bulk_destination" id="bulk-destination" :label="trans('bulk_checkout.destination')">
                            <x-slot:input><select id="bulk-destination" class="form-control"><option value="">{{ trans('bulk_checkout.destination') }}</option>@foreach($locations as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></x-slot:input>
                        </x-form.row>
                    </div>
                    <div class="bulk-target" data-target="project" hidden>
                        <x-form.row name="bulk_project" id="bulk-project" :label="trans('bulk_checkout.modes.project')">
                            <x-slot:input><select id="bulk-project" class="form-control"><option value="">{{ trans('bulk_checkout.select_project') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }} ({{ $project->code }})</option>@endforeach</select></x-slot:input>
                        </x-form.row>
                    </div>
                    <details id="bulk-user-project" class="bulk-context-options"><summary>{{ trans('bulk_checkout.allocation') }}</summary>
                        <x-form.row name="project_allocation" id="bulk-allocation" :label="trans('bulk_checkout.allocation')">
                            <x-slot:input><select id="bulk-allocation" placeholder="{{ trans('bulk_checkout.no_project') }}" class="form-control"><option value="">{{ trans('bulk_checkout.no_project') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }} ({{ $project->code }})</option>@endforeach</select></x-slot:input>
                        </x-form.row>
                    </details>
                </div>
            </div>
        </section>
        <section class="box box-default bulk-items-card">
            <div class="box-body">
                <div class="bulk-section-title"><h2>{{ trans('bulk_checkout.scan_title') }}</h2></div>
                <details id="bulk-stock-scope" class="bulk-stock-scope"><summary>{{ trans('bulk_checkout.stock_source') }}<span id="bulk-source-summary"></span></summary><div class="bulk-scope-fields"><div class="bulk-fields">                    <x-form.row name="source_location_id" id="bulk-source-office" :label="trans('bulk_checkout.stock_at')">
                        <x-slot:input><select id="bulk-source-office" placeholder="{{ trans('bulk_checkout.all_locations') }}" class="form-control"><option value="">{{ trans('bulk_checkout.all_locations') }}</option>@foreach($locations as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></x-slot:input>
                    </x-form.row>
</div>                <div class="bulk-source-controls">
                    <label for="bulk-source-type">{{ trans('bulk_checkout.source') }}</label>
                    <select class="form-control" id="bulk-source-type" data-no-field-ui>
                        @foreach(trans('bulk_checkout.sources') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <div id="bulk-order-wrap" hidden><label class="sr-only" for="bulk-source-order">{{ trans('general.order_number') }}</label><input class="form-control" id="bulk-source-order" placeholder="{{ trans('general.order_number') }}" maxlength="191" data-no-field-ui></div>
                    <div id="bulk-supplier-wrap" hidden><label class="sr-only" for="bulk-source-supplier">{{ trans('general.supplier') }}</label><select class="form-control" id="bulk-source-supplier" data-no-field-ui><option value="">{{ trans('general.select_supplier') }}</option>@foreach($suppliers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                </div>
                <p class="help-block">{{ trans('bulk_checkout.source_help') }}</p>
</div></details>
                <div class="bulk-scanner-wrap"><label for="bulk-scan">{{ trans('bulk_checkout.scan_label') }}</label>
                <div class="bulk-scanner"><input id="bulk-scan" class="form-control" placeholder="{{ trans('bulk_checkout.scan_placeholder') }}" autocomplete="off" data-no-field-ui><button class="btn btn-primary" type="button" id="bulk-scan-add">{{ trans('bulk_checkout.add') }}</button></div>
                <p class="help-block">{{ trans('bulk_checkout.scan_help') }}</p></div>
                <div class="bulk-picker-actions"><button type="button" class="btn btn-default" data-picker="asset" aria-expanded="false" aria-controls="bulk-stock-picker">{{ trans('bulk_checkout.browse') }}</button><button type="button" class="btn btn-default" data-picker="accessory" aria-expanded="false" aria-controls="bulk-stock-picker">{{ trans('bulk_checkout.accessories') }}</button></div>
                <div id="bulk-feedback" class="bulk-feedback" role="status" hidden></div>
                <div id="bulk-stock-picker" class="bulk-stock-picker" hidden>
                    <div class="bulk-picker-heading"><h3>{{ trans('bulk_checkout.stock_picker') }}</h3><label class="sr-only" for="bulk-stock-search">{{ trans('bulk_checkout.search') }}</label><input id="bulk-stock-search" type="search" class="form-control" placeholder="{{ trans('bulk_checkout.search') }}" data-no-field-ui></div>
                    <p class="help-block">{{ trans('bulk_checkout.search_hint') }}</p>
                    <div class="bulk-stock-table"><table class="table"><thead><tr><th>{{ trans('bulk_checkout.item') }}</th><th>{{ trans('bulk_checkout.remaining') }}</th><th>{{ trans('bulk_checkout.quantity') }}</th><th><span class="sr-only">{{ trans('bulk_checkout.add') }}</span></th></tr></thead><tbody id="bulk-stock-rows"></tbody></table></div>
                </div>
                <details class="bulk-batch-defaults"><summary>{{ trans('bulk_checkout.defaults') }}</summary><div class="bulk-default-fields">
                    <div id="bulk-status-wrap"><x-form.row name="bulk_status_id" id="bulk-status" :label="trans('bulk_checkout.status')"><x-slot:input><select class="form-control" id="bulk-status" placeholder="{{ trans('general.do_not_change') }}">@foreach($statuses as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></x-slot:input></x-form.row></div>
                    <p class="help-block" id="bulk-fixed-status" hidden>{{ trans('bulk_checkout.fixed_status') }}</p>
                    <x-form.row name="bulk_condition" id="bulk-condition" :label="trans('bulk_checkout.condition')"><x-slot:input><select class="form-control" id="bulk-condition" placeholder="{{ trans('bulk_checkout.no_condition') }}"><option value="">{{ trans('bulk_checkout.no_condition') }}</option>@foreach(trans('bulk_checkout.conditions') as $value => $name)<option value="{{ $value }}">{{ $name }}</option>@endforeach</select></x-slot:input></x-form.row>
                    <x-form.row name="checkout_at" type="datetimepicker" :label="trans('admin/hardware/form.checkout_date')" />
                    <div id="bulk-return-wrap"><x-form.row name="expected_checkin" type="datepicker" :default_now="false" :label="trans('admin/hardware/form.expected_checkin')" /></div>
                    <x-form.row name="note" type="textarea" :rows="3" :label="trans('general.notes')" />
                </div></details>
            </div>
        </section>
        <section class="box box-default bulk-review-card">
            <div class="box-body">
                <div class="bulk-section-title bulk-summary-heading"><h2>{{ trans('bulk_checkout.summary') }}</h2><span id="bulk-review-state" class="bulk-review-state">{{ trans('bulk_checkout.draft') }}</span></div>
                <div class="bulk-review-overview"><div><span id="bulk-summary-action">{{ trans('bulk_checkout.actions.user') }}</span><strong id="bulk-summary-target">...</strong></div><div><span>{{ trans('bulk_checkout.assets_count') }}</span><strong id="bulk-assets-count">0</strong></div><div><span>{{ trans('bulk_checkout.accessories_count') }}</span><strong id="bulk-accessories-count">0</strong></div></div>
                <div class="bulk-session-list-heading"><h3>{{ trans('bulk_checkout.queue') }}</h3><span id="bulk-session-count">0</span></div>
                <div id="bulk-session-empty" class="bulk-empty"><strong>{{ trans('bulk_checkout.empty_title') }}</strong><p>{{ trans('bulk_checkout.empty') }}</p></div>
                <ul class="bulk-session-list" id="bulk-session-list"></ul>
                <details class="bulk-batch-defaults" id="bulk-current-assignments"><summary>{{ trans('bulk_checkout.current_assignments') }}</summary><div class="bulk-default-fields"><p class="help-block">{{ trans('bulk_checkout.assignments_hint') }}</p><div id="bulk-assignment-rows" aria-live="polite">{{ trans('bulk_checkout.choose_target') }}</div></div></details>
            </div>
            <div class="box-footer"><p class="bulk-review-hint" id="bulk-next-action" role="status"></p><label class="bulk-review-check"><input id="bulk-reviewed" type="checkbox" data-no-field-ui> {{ trans('bulk_checkout.reviewed') }}</label>
<button id="bulk-clear" class="btn btn-default" type="button">{{ trans('bulk_checkout.clear') }}</button><button id="bulk-finalize" class="btn btn-primary" type="submit" disabled>{{ trans('bulk_checkout.actions.user') }}</button></div>
        </section>
        <section id="bulk-completed" class="box box-default" hidden><div class="box-body"><h2 id="bulk-success-message"></h2><p id="bulk-success-target"></p><a class="btn btn-primary" id="bulk-receipt-link">{{ trans('bulk_checkout.view_receipt') }}</a> <a class="btn btn-default" href="{{ route('hardware.bulkcheckout.show') }}">{{ trans('bulk_checkout.another') }}</a></div></section>
    </form>
</div>
@stop
@push('css')<link rel="stylesheet" href="{{ asset('css/bulk-checkout-session.css') }}?v=9">@endpush
@push('js')<script defer src="{{ asset('js/bulk-checkout-session.js') }}?v=10" nonce="{{ csrf_token() }}"></script>@endpush
