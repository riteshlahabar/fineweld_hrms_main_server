<div class="modal-body">
    <div class="row">
        {{-- Employee Info Header --}}
        <div class="col-12 mb-4">
            <div class="card bg-light border-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #137FEC 0%, #0d5db8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="ti ti-user-circle text-white" style="font-size: 28px;"></i>
                            </div>
                        </div>
                        <div>
                            <h5 class="mb-1 fw-bold">{{ $employee->name }}</h5>
                            <p class="text-muted mb-0">
                                <i class="ti ti-calendar me-1"></i>
                                {{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Visits Content --}}
        <div class="col-12">
            @if($visits->isEmpty())
                {{-- No Visits Message --}}
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="ti ti-map-pin-off" style="font-size: 80px; color: #ddd;"></i>
                    </div>
                    <h5 class="text-muted">{{ __('No Client Visits Recorded') }}</h5>
                    <p class="text-muted">{{ __('This employee has not logged any client visits for this date.') }}</p>
                </div>
            @else
                {{-- Total Visits Badge --}}
                <div class="mb-3">
                    <span class="badge bg-primary" style="font-size: 14px; padding: 8px 16px;">
                        <i class="ti ti-users me-1"></i>
                        {{ __('Total Visits:') }} {{ $visits->count() }}
                    </span>
                </div>

                {{-- Visits Table --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>{{ __('Client Name') }}</th>
                                <th>{{ __('Address') }}</th>
                                <th style="width: 120px;">{{ __('Time') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($visits as $index => $visit)
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark">{{ $index + 1 }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-2">
                                                <div style="width: 35px; height: 35px; background-color: #e3f2fd; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                    <i class="ti ti-user" style="color: #137FEC;"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <strong>{{ $visit->client_name }}</strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-start">
                                            <i class="ti ti-map-pin text-danger me-2 mt-1" style="font-size: 16px;"></i>
                                            <span class="text-muted" style="font-size: 13px;">{{ $visit->address }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: #e8f5e9; color: #2e7d32; padding: 8px 12px;">
                                            <i class="ti ti-clock me-1"></i>
                                            {{ \Carbon\Carbon::parse($visit->time)->format('h:i A') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Visit Summary --}}
                <div class="card mt-3 border-0" style="background-color: #f8f9fa;">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="mb-1">
                                    <i class="ti ti-map-2 text-primary" style="font-size: 24px;"></i>
                                </div>
                                <h6 class="mb-0">{{ $visits->count() }}</h6>
                                <small class="text-muted">Total Visits</small>
                            </div>
                            <div class="col-4">
                                <div class="mb-1">
                                    <i class="ti ti-clock text-success" style="font-size: 24px;"></i>
                                </div>
                                <h6 class="mb-0">{{ $visits->first()->time ?? '-' }}</h6>
                                <small class="text-muted">First Visit</small>
                            </div>
                            <div class="col-4">
                                <div class="mb-1">
                                    <i class="ti ti-clock text-success" style="font-size: 24px;"></i>
                                </div>
                                <h6 class="mb-0">{{ $visits->last()->time ?? '-' }}</h6>
                                <small class="text-muted">Last Visit</small>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            {{-- Break Records Section --}}
<div class="col-12 mt-4">
    <h6 class="mb-3">
        <i class="ti ti-coffee text-warning"></i>
        {{ __('Break Records') }}
    </h6>
    
    @if($breaks->isEmpty())
        <div class="alert alert-light text-center" role="alert">
            <i class="ti ti-info-circle"></i>
            <small>{{ __('No breaks taken on this date.') }}</small>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead style="background-color: #fff3cd;">
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>{{ __('Start Time') }}</th>
                        <th>{{ __('End Time') }}</th>
                        <th>{{ __('Duration') }}</th>
                        <th style="width: 100px;">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($breaks as $index => $break)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <span class="badge bg-warning text-dark">
                                    <i class="ti ti-clock"></i>
                                    {{ \Carbon\Carbon::parse($break->break_start)->format('h:i A') }}
                                </span>
                            </td>
                            <td>
                                @if($break->break_end)
                                    <span class="badge bg-success">
                                        <i class="ti ti-clock"></i>
                                        {{ \Carbon\Carbon::parse($break->break_end)->format('h:i A') }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($break->duration)
                                    <strong>{{ $break->getFormattedDuration() }}</strong>
                                @else
                                    <span class="text-muted">In progress...</span>
                                @endif
                            </td>
                            <td>
                                @if($break->status == 'active')
                                    <span class="badge bg-danger">
                                        <i class="ti ti-circle-dot"></i>
                                        Active
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        Completed
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot style="background-color: #f8f9fa;">
                    <tr>
                        <td colspan="3" class="text-end"><strong>{{ __('Total Break Time:') }}</strong></td>
                        <td colspan="2">
                            <strong class="text-warning">
                                @php
                                    $totalMinutes = $breaks->sum('duration');
                                    $hours = floor($totalMinutes / 60);
                                    $mins = $totalMinutes % 60;
                                    echo $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
                                @endphp
                            </strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="ti ti-x me-1"></i>
        {{ __('Close') }}
    </button>
</div>

<style>
    .table thead th {
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6;
    }
    .table tbody tr {
        transition: background-color 0.2s;
    }
    .table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .table tbody td {
        vertical-align: middle;
        padding: 12px 8px;
    }
</style>
