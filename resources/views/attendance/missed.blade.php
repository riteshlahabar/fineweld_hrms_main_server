@extends('layouts.admin')

@section('page-title')
    {{ __('Missed Attendance') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Home') }}</a>
    </li>
    <li class="breadcrumb-item">
        {{ __('Missed Attendance') }}
    </li>
@endsection

@push('script-page')
    <script>
        function exportTableToExcel(tableID, filename = 'missed-attendance.xls') {
            let table = document.getElementById(tableID).outerHTML;
            let dataType = 'application/vnd.ms-excel';
            let link = document.createElement('a');

            document.body.appendChild(link);

            link.href = 'data:' + dataType + ', ' + encodeURIComponent(table);
            link.download = filename;
            link.click();

            document.body.removeChild(link);
        }

        document.addEventListener("DOMContentLoaded", function () {

            if (document.getElementById("missed-attendance-table")) {

                new simpleDatatables.DataTable("#missed-attendance-table", {
                    perPage: 10,
                    perPageSelect: [10, 25, 50, 100, 500],
                    searchable: true,
                    sortable: true,
                    fixedHeight: false
                });

            }

        });
    </script>
@endpush

@section('action-button')
@endsection

@section('content')

    <div class="row">

        <div class="col-sm-12">

            <div class="mt-2" id="multiCollapseExample1">

                <div class="card">

                    <div class="card-body">

                        {{ Form::open([
                            'route' => ['attendanceemployee.missedattendance'],
                            'method' => 'get',
                            'id' => 'missed_attendance_filter'
                        ]) }}

                        <div class="row align-items-center justify-content-end">

                            <div class="col-xl-10">

                                <div class="row">

                                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">

                                        <div class="btn-box">

                                            {{ Form::label('month', __('Month'), ['class' => 'form-label']) }}

                                            {{ Form::month(
                                                'month',
                                                isset($_GET['month']) ? $_GET['month'] : date('Y-m'),
                                                ['class' => 'month-btn form-control month-btn']
                                            ) }}

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="col-auto mt-4">

                                <div class="row">

                                    <div class="col-auto">

                                        <a href="#"
                                           class="btn btn-sm btn-primary me-1"
                                           onclick="document.getElementById('missed_attendance_filter').submit(); return false;"
                                           data-bs-toggle="tooltip"
                                           title="{{ __('Apply') }}">

                                            <span class="btn-inner--icon">
                                                <i class="ti ti-search"></i>
                                            </span>

                                        </a>

                                        <a href="{{ route('attendanceemployee.missedattendance') }}"
                                           class="btn btn-sm btn-danger me-1"
                                           data-bs-toggle="tooltip"
                                           title="{{ __('Reset') }}">

                                            <span class="btn-inner--icon">
                                                <i class="ti ti-refresh text-white-off"></i>
                                            </span>

                                        </a>

                                        <button type="button"
                                                onclick="exportTableToExcel('missed-attendance-table', 'missed-attendance.xls')"
                                                class="btn btn-sm btn-success"
                                                data-bs-toggle="tooltip"
                                                title="{{ __('Export') }}">

                                            <i class="ti ti-download"></i>

                                        </button>

                                    </div>

                                </div>

                            </div>

                        </div>

                        {{ Form::close() }}

                    </div>

                </div>

            </div>

        </div>

        <div class="col-xl-12">

            <div class="card">

                <div class="card-header card-body table-border-style">

                    <div class="table-responsive">

                        <table class="table" id="missed-attendance-table">

                            <thead>

                                <tr>

                                    <th>{{ __('Sr. No.') }}</th>

                                    <th>{{ __('Employee') }}</th>

                                    <th class="text-center">
                                        {{ __('Missed Punches') }}
                                    </th>

                                    <th class="text-center">
                                        {{ __('Total Punched') }}
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                @foreach ($report as $key => $row)

                                    <tr>

                                        <td>{{ $key + 1 }}</td>

                                        <td>
                                            {{ $row->employee_name ?? __('Unknown') }}
                                        </td>

                                        <td class="text-center text-danger fw-bold">
                                            {{ $row->missed }}
                                        </td>

                                        <td class="text-center">
                                            {{ $row->punched }}
                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                    @if(count($report) == 0)

                        <div class="text-center p-3">

                            {{ __('No data found.') }}

                        </div>

                    @endif

                </div>

            </div>

        </div>

    </div>

@endsection