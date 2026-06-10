@php
    $plan = App\Models\Utility::getChatGPTSettings();
@endphp

{{ Form::model($leave, ['route' => ['leave.update', $leave->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">

    @if ($plan->enable_chatgpt == 'on')
        <div class="card-footer text-end">
            <a href="#" class="btn btn-sm btn-primary" data-size="medium" data-ajax-popup-over="true"
                data-url="{{ route('generate', ['leave']) }}" data-bs-toggle="tooltip" data-bs-placement="top"
                title="{{ __('Generate') }}" data-title="{{ __('Generate Content With AI') }}">
                <i class="fas fa-robot"></i>{{ __(' Generate With AI') }}
            </a>
        </div>
    @endif

    @if (\Auth::user()->type != 'employee')
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    {{ Form::label('employee_id', __('Employee'), ['class' => 'col-form-label']) }}<x-required></x-required>
                    {{ Form::select('employee_id', $employees, null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('Select Employee')]) }}
                </div>
            </div>
        </div>
    @else
        {!! Form::hidden('employee_id', !empty($employees) ? $employees->id : 0, ['id' => 'employee_id']) !!}
    @endif
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('leave_type_id', __('Leave Type'), ['class' => 'col-form-label']) }}<x-required></x-required>
                <select name="leave_type_id" id="leave_type_id" class="form-control select" required>
                    @foreach ($leavetypes as $leaveType)
                        <option value="{{ $leaveType->id }}" @selected(old('leave_type_id', $leave->leave_type_id) == $leaveType->id)>{{ $leaveType->title }} (<p class="float-right pr-5">
                                {{ $leaveType->days }}</p>)</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('duration_type', __('Leave Duration'), ['class' => 'col-form-label']) }}<x-required></x-required>
                <select name="duration_type" id="duration_type" class="form-control" required>
                    <option value="full_day" @selected(old('duration_type', $leave->duration_type ?? 'full_day') == 'full_day')>{{ __('Full Day') }}</option>
                    <option value="half_day" @selected(old('duration_type', $leave->duration_type) == 'half_day')>{{ __('Half Day') }}</option>
                </select>
            </div>
        </div>
        <div class="col-md-6" id="half_day_type_wrapper" style="display: none;">
            <div class="form-group">
                {{ Form::label('half_day_type', __('Half Day Session'), ['class' => 'col-form-label']) }}<x-required></x-required>
                <select name="half_day_type" id="half_day_type" class="form-control">
                    <option value="">{{ __('Select Half Day Session') }}</option>
                    <option value="first_half" @selected(old('half_day_type', $leave->half_day_type) == 'first_half')>{{ __('First Half') }}</option>
                    <option value="second_half" @selected(old('half_day_type', $leave->half_day_type) == 'second_half')>{{ __('Second Half') }}</option>
                </select>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('start_date', __('Start Date'), ['class' => 'col-form-label']) }}<x-required></x-required>
                {{ Form::text('start_date', null, ['class' => 'form-control d_week', 'id' => 'start_date', 'required' => 'required', 'autocomplete' => 'off']) }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('end_date', __('End Date'), ['class' => 'col-form-label']) }}<x-required></x-required>
                {{ Form::text('end_date', null, ['class' => 'form-control d_week', 'id' => 'end_date', 'required' => 'required', 'autocomplete' => 'off']) }}
                <small class="text-muted" id="half_day_note" style="display: none;">
                    {{ __('For half day leave, end date will match start date automatically.') }}
                </small>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('leave_reason', __('Leave Reason'), ['class' => 'col-form-label']) }}<x-required></x-required>
                {{ Form::textarea('leave_reason', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('Leave Reason'), 'rows' => '3']) }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">

                {{ Form::label('remark', __('Remark'), ['class' => 'col-form-label']) }}<x-required></x-required>

                @if ($plan->enable_chatgpt == 'on')
                    <a href="#" data-size="md" class="btn btn-primary btn-icon btn-sm" data-ajax-popup-over="true"
                        id="grammarCheck" data-url="{{ route('grammar', ['grammar']) }}" data-bs-placement="top"
                        data-title="{{ __('Grammar check with AI') }}">
                        <i class="ti ti-rotate"></i> <span>{{ __('Grammar check with AI') }}</span>
                    </a>
                @endif

                {{ Form::textarea('remark', null, ['class' => 'form-control grammer_textarea', 'required' => 'required', 'placeholder' => __('Leave Remark'), 'rows' => '3']) }}
            </div>
        </div>
    </div>
    @role('Company')
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    {{ Form::label('status', __('Status'), ['class' => 'col-form-label']) }}
                    <select name="status" id="" class="form-control select2">
                        <option value="">{{ __('Select Status') }}</option>
                        <option value="Pending" @if ($leave->status == 'Pending') selected="" @endif>{{ __('Pending') }}
                        </option>
                        <option value="Approved" @if ($leave->status == 'Approved') selected="" @endif>{{ __('Approved') }}
                        </option>
                        <option value="Reject" @if ($leave->status == 'Reject') selected="" @endif>{{ __('Reject') }}
                        </option>
                    </select>
                </div>
            </div>
        </div>
    @endrole
</div>
<div class="modal-footer">
    <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
    <input type="submit" value="{{ __('Update') }}" class="btn  btn-primary">

</div>
{{ Form::close() }}

<script>
    $(document).ready(function() {
        setTimeout(() => {
            var employee_id = $('#employee_id').val();
            if (employee_id) {
                $('#employee_id').trigger('change');
            }
        }, 100);

        function toggleHalfDayFields() {
            var isHalfDay = $('#duration_type').val() === 'half_day';

            $('#half_day_type_wrapper').toggle(isHalfDay);
            $('#half_day_note').toggle(isHalfDay);
            $('#end_date').prop('readonly', isHalfDay);

            if (isHalfDay) {
                $('#end_date').val($('#start_date').val());
            } else {
                $('#half_day_type').val('');
            }
        }

        $('#duration_type, #start_date').on('change', function() {
            toggleHalfDayFields();
        });

        toggleHalfDayFields();
    });
</script>
