@extends('layouts/default')
@section('title') {{ trans('bulk_checkout.receipt') }} @parent @stop
@section('content')
<section class="box box-default"><div class="box-header with-border"><h2 class="box-title">{{ trans('bulk_checkout.saved.'.$batch->mode) }}</h2></div><div class="box-body">
<dl><dt>{{ trans('bulk_checkout.reference') }}</dt><dd>{{ $batch->id }}</dd><dt>{{ trans('bulk_checkout.target') }}</dt><dd>{{ $batch->target_name }}</dd><dt>{{ trans('bulk_checkout.recorded') }}</dt><dd>{{ $batch->created_at }}</dd></dl>
@if($batch->mode !== 'user')<p>{{ trans('bulk_checkout.hints.'.$batch->mode) }}</p>@endif
<div class="table-responsive"><table class="table"><thead><tr><th>{{ trans('bulk_checkout.item') }}</th><th>{{ trans('bulk_checkout.quantity') }}</th><th>{{ trans('bulk_checkout.status') }}</th><th>{{ trans('bulk_checkout.condition') }}</th></tr></thead><tbody>@foreach($items as $item)<tr><td>{{ $item['name'] }}@if($item['tag'])<br>{{ $item['tag'] }}@endif</td><td>{{ $item['quantity'] }}</td><td>{{ $item['status'] ?? '' }}</td><td>{{ $item['condition'] ? trans('bulk_checkout.conditions.'.$item['condition']) : trans('bulk_checkout.no_condition') }}</td></tr>@endforeach</tbody></table></div>
@if($batch->note)<h3>{{ trans('general.notes') }}</h3><p>{{ $batch->note }}</p>@endif
<a href="{{ route('hardware.bulkcheckout.show') }}" class="btn btn-primary">{{ trans('bulk_checkout.another') }}</a>
</div></section>
@stop
