@extends('layouts.admin')

@section('page-title')
    {{ __('Manage Employee Salary') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Employee Salary') }}</li>
@endsection


@section('content')
    <div class="row">

        <div class="col-md-12 col-lg-12 col-sm-12 col-xl-12">
            <div class="card">
                <div class="card-header card-body table-border-style">
                    <div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
        <i class="ti ti-upload"></i> Import Salary
    </button>
</div>
                    {{-- <h5></h5> --}}
                    <div class="table-responsive">
                        <table class="table" id="pc-dt-simple">
                            <thead>
                                <tr>
                                    <th>{{ __('Employee Id') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Payroll Type') }}</th>
                                    <th>{{ __('Salary') }}</th>
                                    <th>{{ __('Net Salary') }}</th>
                                    <th width="200px">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($employees as $employee)
                                    <tr>
                                        <td>
                                            <a href="{{ route('setsalary.show', \Illuminate\Support\Facades\Crypt::encrypt($employee->id)) }}"
                                                class="btn btn-outline-primary">
                                                {{ \Auth::user()->employeeIdFormat($employee->employee_id) }}
                                            </a>
                                        </td>
                                        <td>{{ $employee->name }}</td>
                                        <td>{{ !empty($employee->salary_type()) ? $employee->salary_type() : '-' }}</td>
                                        <td>{{ \Auth::user()->priceFormat($employee->salary) }}</td>
                                        <td>{{ !empty($employee->get_net_salary()) ? \Auth::user()->priceFormat($employee->get_net_salary()) : '-' }}
                                        </td>
                                        <td class="Action">
                                            @if ($employee->user->is_active == 1 && $employee->user->is_disable == 1)
                                            <div class="dt-buttons">
                                                    <span>
                                                        <div class="action-btn bg-warning">
                                                            <a href="{{ route('setsalary.show', \Illuminate\Support\Facades\Crypt::encrypt($employee->id)) }}"
                                                                class="mx-3 btn btn-sm  align-items-center"
                                                                data-bs-toggle="tooltip" title=""
                                                                data-bs-original-title="{{ __('View') }}">
                                                                <span class="text-white"><i class="ti ti-eye"></i></span>
                                                            </a>
                                                        </div>
                                                    </span>
                                                </div>
                                            @else
                                                <i class="ti ti-lock"></i>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('salary.import.save') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Import Salary CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="file" name="file" class="form-control" required>
                    <small class="text-muted">Only CSV file allowed</small>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
