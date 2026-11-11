@extends('layouts/default', [
    'helpTitle' => trans('admin/kits/general.about_kits_title'),
    'helpText' => trans('admin/kits/general.about_kits_text')])

{{-- Web site Title --}}
@section('title')
  {{ trans('general.kits') }}
@parent
@stop

{{-- Content --}}
@section('content')
    <section class="kit-workspace-intro">
        <div><p class="kit-eyebrow">{{ trans('admin/kits/general.template_label') }}</p><h2>{{ trans('admin/kits/general.workspace_title') }}</h2><p>{{ trans('admin/kits/general.workspace_help') }}</p></div>
        <ol class="kit-process-guide" aria-label="{{ trans('general.kits') }}">
            <li><span>1</span>{{ trans('admin/kits/general.define_contents') }}</li>
            <li><span>2</span>{{ trans('admin/kits/general.review_stock') }}</li>
            <li><span>3</span>{{ trans('admin/kits/general.assign_person') }}</li>
        </ol>
    </section>
    <x-container>
        <x-box name="kits" sr_only_title>

            <x-slot:table_header>{{ trans('general.kits') }}</x-slot:table_header>

            <x-table
                :presenter="\App\Presenters\PredefinedKitPresenter::dataTableLayout()"
                :fixed_number="1"
                :fixed_right_number="2"
                buttons="kitButtons"
                api_url="{{ route('api.kits.index') }}"
                export_filename="export-kits-{{ date('Y-m-d') }}"
            />
        </x-box>
    </x-container>
@stop
@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'kits-export', 'search' => true])
@stop
