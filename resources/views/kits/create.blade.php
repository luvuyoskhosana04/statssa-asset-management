@extends('layouts/edit-form', [
    'createText' =>  trans('admin/kits/general.create'),
    'updateText' => trans('admin/kits/general.update'),
    'formAction' => (isset($item->id)) ? route('kits.update', ['kit' => $item->id]) : route('kits.store'),
])

{{-- Page content --}}
@section('inputFields')
<div class="kit-definition-help"><p class="kit-eyebrow">{{ trans('admin/kits/general.template_label') }}</p><p>{{ trans('admin/kits/general.create_help') }}</p></div>
@include ('partials.forms.edit.name', ['translated_name' => trans('general.name')])
@stop
