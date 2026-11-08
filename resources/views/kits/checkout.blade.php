@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/kits/general.checkout') }}
    @parent
@stop

{{-- Page content --}}
@section('content')

    <style>
        .input-group {
            padding-left: 0px !important;
        }
    </style>

    <x-container columns="2">
        <x-page-column class="col-md-7">

            <x-form data-stepper="kit" id="checkout_form" route="{{ url()->current() }}">

                <x-box>
                    <section data-stepper-section="1"><x-kit-summary :kit="$kit" /></section>

                    <x-input.user-select
                        :label="trans('general.select_user')"
                        name="user_id"
                        :selected="old('user_id')"
                        required
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
                            <textarea class="form-control" id="note" name="note">{{ old('note') }}</textarea>
                        </x-slot:input>
                    </x-form.row>

                    <x-slot:customfooter>
                        <div class="box-footer">
                            <a class="btn btn-link" href="{{ route('kits.index') }}">{{ trans('button.cancel') }}</a>
                            <div class="text-right"><button type="submit" class="btn btn-success"><x-icon type="checkmark"/> {{ trans('general.checkout') }}</button></div>
                        </div>
                    </x-slot:customfooter>

                </x-box>

            </x-form>

        </x-page-column>

        <x-page-column class="col-md-5">
            <livewire:checkout-target-panel type="assets" />
        </x-page-column>

    </x-container>
@stop

@section('notifications')
    @parent
@stop
