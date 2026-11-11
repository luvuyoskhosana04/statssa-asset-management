@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/kits/general.checkout') }}
    @parent
@stop

{{-- Page content --}}
@section('content')

    <div class="kit-checkout-workspace">
    <div class="kit-checkout-context"><span class="kit-eyebrow">{{ trans('admin/kits/general.checkout_context') }}</span><strong>{{ $kit->name }}</strong></div>
    <x-container columns="2">
        <x-page-column class="col-md-7">

            <x-form data-stepper="kit" id="checkout_form" route="{{ url()->current() }}">

                <x-box>
                    <section data-stepper-section="0" class="kit-recipient-help"><p>{{ trans('admin/kits/general.recipient_help') }}</p></section>
                    <section data-stepper-section="1"><x-kit-summary :kit="$kit" /></section>

                    <x-input.user-select
                        :label="trans('general.select_user')"
                        name="user_id"
                        :selected="old('user_id')"
                        required
                        :hideNewButton="true"
                    />

                    <x-form.row
                        :label="trans('admin/hardware/form.checkout_date')"
                        name="checkout_at"
                        type="datetimepicker"
                        input_div_class="col-md-4"
                    />

                    <x-form.row
                        :label="trans('admin/hardware/form.expected_checkin')"
                        name="expected_checkin"
                        type="datetimepicker"
                        :default_now="false"
                        input_div_class="col-md-4"
                    />

                    <x-form.row
                        :label="trans('general.notes')"
                        name="note"
                    >
                        <x-slot:input>
                            <textarea class="form-control" id="note" name="note" rows="3" placeholder="{{ trans('admin/kits/general.checkout_notes_example') }}">{{ old('note') }}</textarea>
                        </x-slot:input>
                    </x-form.row>

                    <x-slot:customfooter>
                        <div class="box-footer">
                            <a class="btn btn-link" href="{{ route('kits.index') }}">{{ trans('button.cancel') }}</a>
                            <div class="text-right"><button type="submit" class="btn btn-success"><x-icon type="checkmark"/> {{ trans('admin/kits/general.checkout_action') }}</button></div>
                        </div>
                    </x-slot:customfooter>

                </x-box>

            </x-form>

        </x-page-column>

        <x-page-column class="col-md-5">
            <aside class="box box-default kit-checkout-help">
                <div class="box-body">
                    <p class="kit-eyebrow">{{ trans('admin/kits/general.template_label') }}</p>
                    <h2>{{ $kit->name }}</h2>
                    <p>{{ trans('admin/kits/general.template_help') }}</p>
                    <ul class="kit-compact-contents" aria-label="{{ trans('presentation.kit_summary') }}">
                        @forelse ($kit->present()->bundleContents() as $content)
                            <li><span>{{ $content['name'] }}<small>{{ $content['type'] }}</small></span><strong>{{ $content['quantity'] }} &times;</strong></li>
                        @empty
                            <li>{{ trans('admin/kits/general.stock_empty') }}</li>
                        @endforelse
                    </ul>
                    <h3>{{ trans('admin/kits/general.automatic_selection') }}</h3>
                    <p>{{ trans('admin/kits/general.automatic_selection_help') }}</p>
                    <p class="kit-return-note">{{ trans('admin/kits/general.return_help') }}</p>
                </div>
            </aside>
            <livewire:checkout-target-panel type="assets" />
        </x-page-column>

    </x-container>
    </div>
@stop

@section('notifications')
    @parent
@stop
