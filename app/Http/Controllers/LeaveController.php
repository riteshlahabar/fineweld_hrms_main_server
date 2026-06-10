<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave as LocalLeave;
use App\Models\LeaveType;
use App\Mail\LeaveActionSend;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Imports\EmployeesImport;
use App\Exports\LeaveExport;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\GoogleCalendar\Event as GoogleEvent;

class LeaveController extends Controller
{
    private function normalizeDurationType(?string $durationType): string
    {
        return $durationType === 'half_day' ? 'half_day' : 'full_day';
    }

    private function calculateTotalLeaveDays(string $startDate, string $endDate, string $durationType): float
    {
        if ($durationType === 'half_day') {
            return 0.5;
        }

        $startDate = new \DateTime($startDate);
        $endDate = new \DateTime($endDate);
        $endDate->add(new \DateInterval('P1D'));

        return !empty($startDate->diff($endDate)) ? $startDate->diff($endDate)->days : 0;
    }

    private function getExpandedLeaveDates(string $startDate, string $endDate): array
    {
        $dates = [];
        $currentDate = new \DateTime($startDate);
        $lastDate = new \DateTime($endDate);

        while ($currentDate <= $lastDate) {
            $dates[] = $currentDate->format('d/m/Y');
            $currentDate->modify('+1 day');
        }

        return $dates;
    }

    public function index()
    {

        if (\Auth::user()->can('Manage Leave')) {
            if (\Auth::user()->type == 'employee') {
                $user     = \Auth::user();
                $employee = Employee::where('user_id', '=', $user->id)->first();
                $leaves = LocalLeave::where('employee_id', '=', $employee->id)->get();
            } else {
               $leaves = LocalLeave::where('created_by', '=', \Auth::user()->creatorId())
    ->with(['employees', 'leaveType'])
    ->orderByRaw("
        CASE 
            WHEN MONTH(start_date) = MONTH(CURDATE()) 
            AND YEAR(start_date) = YEAR(CURDATE())
            THEN 0
            ELSE 1
        END
    ")
    ->orderBy('start_date', 'desc')
    ->get();
            }
            $selectedLeaveMonth = request()->get('leave_month', date('Y-m'));

$employeeMonthWiseLeaves = $this->getEmployeeMonthWiseLeaveSummary($selectedLeaveMonth);

            return view('leave.index', compact(
    'leaves',
    'selectedLeaveMonth',
    'employeeMonthWiseLeaves'
));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    
    public function getEmployeeMonthWiseLeaveSummary($month = null)
{
    $month = $month ?? date('Y-m');
    $creatorId = \Auth::user()->creatorId();
    $annualCycle = Utility::AnnualLeaveCycle();

    $startOfMonth = date('Y-m-01', strtotime($month));
    $endOfMonth   = date('Y-m-t', strtotime($month));

    $query = LocalLeave::with('employees:id,name')
        ->whereBetween('start_date', [$startOfMonth, $endOfMonth])
        ->orderBy('start_date');

    if (\Auth::user()->type == 'employee') {
        $employee = Employee::where('user_id', \Auth::user()->id)->first();
        $query->where('employee_id', $employee->id);
    } else {
        $query->where('created_by', $creatorId);
    }

    $totalAllowedLeaveDays = (float) LeaveType::where('created_by', $creatorId)->sum('days');

    $approvedLeaveDays = LocalLeave::where('status', 'Approved')
        ->whereBetween('created_at', [$annualCycle['start_date'], $annualCycle['end_date']]);

    if (\Auth::user()->type == 'employee') {
        $approvedLeaveDays->where('employee_id', $employee->id);
    } else {
        $approvedLeaveDays->where('created_by', $creatorId);
    }

    $approvedLeaveDays = $approvedLeaveDays
        ->select('employee_id', \DB::raw('SUM(total_leave_days) as used_leave_days'))
        ->groupBy('employee_id')
        ->get()
        ->keyBy('employee_id');

    return $query->get()
        ->groupBy('employee_id')
        ->map(function ($employeeLeaves) use ($approvedLeaveDays, $totalAllowedLeaveDays) {
            $leaveDates = [];
            $employeeId = $employeeLeaves->first()->employee_id;

            foreach ($employeeLeaves as $leave) {
                $leaveDates = array_merge(
                    $leaveDates,
                    $this->getExpandedLeaveDates($leave->start_date, $leave->end_date)
                );
            }

            $usedLeaveDays = isset($approvedLeaveDays[$employeeId]) ? (float) $approvedLeaveDays[$employeeId]->used_leave_days : 0;

            $summary = new \stdClass();
            $summary->employee_id = $employeeId;
            $summary->employees = $employeeLeaves->first()->employees;
            $summary->total_leave_taken = $employeeLeaves->count();
            $summary->total_leave_days = $employeeLeaves->sum(function ($leave) {
                return (float) $leave->total_leave_days;
            });
            $summary->leave_dates = implode(', ', array_values(array_unique($leaveDates)));
            $summary->leave_balance = max($totalAllowedLeaveDays - $usedLeaveDays, 0);

            return $summary;
        })
        ->values();
}

    public function create()
    {
        if (\Auth::user()->can('Create Leave')) {
            if (Auth::user()->type == 'employee') {
                $employees = Employee::where('user_id', '=', \Auth::user()->id)->first();
            } else {
                $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            }
            $leavetypes      = LeaveType::where('created_by', '=', \Auth::user()->creatorId())->get();
            // $leavetypes_days = LeaveType::where('created_by', '=', \Auth::user()->creatorId())->get();

            return view('leave.create', compact('employees', 'leavetypes'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('Create Leave')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'employee_id' => 'required',
                    'leave_type_id' => 'required',
                    'start_date' => 'required',
                    'end_date' => 'required|after_or_equal:start_date',
                    'duration_type' => 'required|in:full_day,half_day',
                    'half_day_type' => 'nullable|required_if:duration_type,half_day|in:first_half,second_half',
                    'leave_reason' => 'required',
                    'remark' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            // $employee = Employee::where('created_by', '=', \Auth::user()->id)->first();
            $leave_type = LeaveType::find($request->leave_type_id);
            $durationType = $this->normalizeDurationType($request->duration_type);
            $halfDayType = $durationType === 'half_day' ? $request->half_day_type : null;

            if ($durationType === 'half_day' && $request->start_date !== $request->end_date) {
                return redirect()->back()->with('error', __('Half day leave must have the same start date and end date.'));
            }

            $date = Utility::AnnualLeaveCycle();

            if (\Auth::user()->type == 'employee') {
                // Leave day
                $leaves_used   = LocalLeave::where('employee_id', '=', $request->employee_id)->where('leave_type_id', $leave_type->id)->where('status', 'Approved')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');

                $leaves_pending  = LocalLeave::where('employee_id', '=', $request->employee_id)->where('leave_type_id', $leave_type->id)->where('status', 'Pending')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');
            } else {
                // Leave day
                $leaves_used   = LocalLeave::where('employee_id', '=', $request->employee_id)->where('leave_type_id', $leave_type->id)->where('status', 'Approved')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');

                $leaves_pending  = LocalLeave::where('employee_id', '=', $request->employee_id)->where('leave_type_id', $leave_type->id)->where('status', 'Pending')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');
            }

            $total_leave_days = $this->calculateTotalLeaveDays($request->start_date, $request->end_date, $durationType);

            $return = $leave_type->days - $leaves_used;
            if ($total_leave_days > $return) {
                return redirect()->back()->with('error', __('You are not eligible for leave.'));
            }

            if (!empty($leaves_pending) && $leaves_pending + $total_leave_days > $return) {
                return redirect()->back()->with('error', __('Multiple leave entry is pending.'));
            }

            if ($leave_type->days >= $total_leave_days) {

                $leave    = new LocalLeave();
                if (\Auth::user()->type == "employee") {
                    $leave->employee_id = $request->employee_id;
                } else {
                    $leave->employee_id = $request->employee_id;
                }
                $leave->leave_type_id    = $request->leave_type_id;
                $leave->applied_on       = date('Y-m-d');
                $leave->start_date       = $request->start_date;
                $leave->end_date         = $request->end_date;
                $leave->duration_type    = $durationType;
                $leave->half_day_type    = $halfDayType;
                $leave->total_leave_days = $total_leave_days;
                $leave->leave_reason     = $request->leave_reason;
                $leave->remark           = $request->remark;
                $leave->status           = 'Pending';
                $leave->created_by       = Auth::user()->creatorId();
                $leave->save();
                $employee = Employee::where('id', $leave->employee_id)->first();
                $user = User::where('id', $employee->created_by)->first();
                if (Auth::user()->type != "company") {
                    $setings = Utility::settings();
                    if ($setings['new_leave_request'] == 1) {

                        $uArr = [
                            'employee_name' => $employee->name,
                            'leave_type' => $leave->leaveType->title,
                            'leave_start_end_time' => (isset($leave->start_date) ? $leave->start_date : '') . ' to ' . (isset($leave->end_date) ? $leave->end_date : ''),
                            'leave_reason' => isset($leave->leave_reason) ? $leave->leave_reason : '',
                        ];

                        $resp = Utility::sendEmailTemplate('new_leave_request', [$user->id => $user->email], $uArr);
                    }
                }

                // Google celander
                if ($request->get('synchronize_type')  == 'google_calender') {

                    $type = 'leave';
                    $request1 = new GoogleEvent();
                    $request1->title = !empty(\Auth::user()->getLeaveType($leave->leave_type_id)) ? \Auth::user()->getLeaveType($leave->leave_type_id)->title : '';
                    $request1->start_date = $request->start_date;
                    $request1->end_date = $request->end_date;
                    Utility::addCalendarData($request1, $type);
                }

                return redirect()->route('leave.index')->with('success', __('Leave successfully created.') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
            } else {
                return redirect()->back()->with('error', __('Leave type ' . $leave_type->title . ' is provide maximum ' . $leave_type->days . "  days please make sure your selected days is under " . $leave_type->days . ' days.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function show(LocalLeave $leave)
    {
        return redirect()->route('leave.index');
    }

    public function edit(LocalLeave $leave)
    {
        if (\Auth::user()->can('Edit Leave')) {
            if ($leave->created_by == \Auth::user()->creatorId()) {

                if (Auth::user()->type == 'employee') {
                    $employees = Employee::where('user_id', '=', \Auth::user()->id)->first();
                } else {
                    $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
                }

                // $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');

                // $leavetypes = LeaveType::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('title', 'id');
                $leavetypes      = LeaveType::where('created_by', '=', \Auth::user()->creatorId())->get();

                return view('leave.edit', compact('leave', 'employees', 'leavetypes'));
            } else {
                return response()->json(['error' => __('Permission denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, $leave)
    {
        $leave = LocalLeave::find($leave);
        if (\Auth::user()->can('Edit Leave')) {
            if ($leave->created_by == Auth::user()->creatorId()) {
                $validator = \Validator::make(
                    $request->all(),
                    [
                    'employee_id' => 'required',
                    'leave_type_id' => 'required',
                    'start_date' => 'required',
                    'end_date' => 'required|after_or_equal:start_date',
                    'duration_type' => 'required|in:full_day,half_day',
                    'half_day_type' => 'nullable|required_if:duration_type,half_day|in:first_half,second_half',
                    'leave_reason' => 'required',
                    'remark' => 'required',
                ]
            );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }
                $leave_type = LeaveType::find($request->leave_type_id);
                $employee = Employee::where('user_id', '=', \Auth::user()->id)->first();
                $durationType = $this->normalizeDurationType($request->duration_type);
                $halfDayType = $durationType === 'half_day' ? $request->half_day_type : null;

                if ($durationType === 'half_day' && $request->start_date !== $request->end_date) {
                    return redirect()->back()->with('error', __('Half day leave must have the same start date and end date.'));
                }

                $date = Utility::AnnualLeaveCycle();

                if (\Auth::user()->type == 'employee') {
                    // Leave day
                    $leaves_used   = LocalLeave::whereNotIn('id', [$leave->id])->where('employee_id', '=', $employee->id)->where('leave_type_id', $leave_type->id)->where('status', 'Approved')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');

                    $leaves_pending  = LocalLeave::whereNotIn('id', [$leave->id])->where('employee_id', '=', $employee->id)->where('leave_type_id', $leave_type->id)->where('status', 'Pending')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');
                } else {
                    // Leave day
                    $leaves_used   = LocalLeave::whereNotIn('id', [$leave->id])->where('employee_id', '=', $request->employee_id)->where('leave_type_id', $leave_type->id)->where('status', 'Approved')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');

                    $leaves_pending  = LocalLeave::whereNotIn('id', [$leave->id])->where('employee_id', '=', $request->employee_id)->where('leave_type_id', $leave_type->id)->where('status', 'Pending')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');
                }

                $total_leave_days = $this->calculateTotalLeaveDays($request->start_date, $request->end_date, $durationType);

                $return = $leave_type->days - $leaves_used;
                if ($total_leave_days > $return) {
                    return redirect()->back()->with('error', __('You are not eligible for leave.'));
                }

                if (!empty($leaves_pending) && $leaves_pending + $total_leave_days > $return) {
                    return redirect()->back()->with('error', __('Multiple leave entry is pending.'));
                }

                if ($leave_type->days >= $total_leave_days) {
                    if (\Auth::user()->type == 'employee') {
                        $leave->employee_id = $employee->id;
                    } else {
                        $leave->employee_id      = $request->employee_id;
                    }
                    $leave->leave_type_id    = $request->leave_type_id;
                    $leave->start_date       = $request->start_date;
                    $leave->end_date         = $request->end_date;
                    $leave->duration_type    = $durationType;
                    $leave->half_day_type    = $halfDayType;
                    $leave->total_leave_days = $total_leave_days;
                    $leave->leave_reason     = $request->leave_reason;
                    $leave->remark           = $request->remark;
                    // $leave->status           = $request->status;

                    $leave->save();

                    return redirect()->route('leave.index')->with('success', __('Leave successfully updated.'));
                } else {
                    return redirect()->back()->with('error', __('Leave type ' . $leave_type->name . ' is provide maximum ' . $leave_type->days . "  days please make sure your selected days is under " . $leave_type->days . ' days.'));
                }
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy(LocalLeave $leave)
    {
        if (\Auth::user()->can('Delete Leave')) {
            if ($leave->created_by == \Auth::user()->creatorId()) {
                $leave->delete();

                return redirect()->route('leave.index')->with('success', __('Leave successfully deleted.'));
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function export()
    {
        $name = 'leave_' . date('Y-m-d i:h:s');
        $data = Excel::download(new LeaveExport(), $name . '.xlsx');

        return $data;
    }

    public function action($id)
    {
        $leave     = LocalLeave::find($id);
        $employee  = Employee::find($leave->employee_id);
        $leavetype = LeaveType::find($leave->leave_type_id);



        return view('leave.action', compact('employee', 'leavetype', 'leave'));
    }

    public function changeaction(Request $request)
    {
        $leave = LocalLeave::find($request->leave_id);

        $leave->status = $request->status;
        if ($leave->status == 'Approved') {
            $total_leave_days        = $this->calculateTotalLeaveDays(
                $leave->start_date,
                $leave->end_date,
                $this->normalizeDurationType($leave->duration_type)
            );
            $leave->total_leave_days = $total_leave_days;
            $leave->status           = 'Approved';
        }

        $leave->save();

        // twilio
        $setting = Utility::settings(\Auth::user()->creatorId());
        $emp = Employee::find($leave->employee_id);
        if (isset($setting['twilio_leave_approve_notification']) && $setting['twilio_leave_approve_notification'] == 1) {
            // $msg = __("Your leave has been") . ' ' . $leave->status . '.';

            $uArr = [
                'leave_status' => $leave->status,
            ];

            // Utility::send_twilio_msg($emp->phone, 'leave_approve_reject', $uArr);
            if (!empty($emp->phone)) {
                Utility::send_twilio_msg($emp->phone, 'leave_approve_reject', $uArr);
            } else {
                return redirect()->route('leave.index')->with('error', __('Employee phone number is missing.'));
            }
        }

        $setings = Utility::settings();

        if ($setings['leave_status'] == 1) {
            $employee     = Employee::where('id', $leave->employee_id)->where('created_by', '=', \Auth::user()->creatorId())->first();

            $uArr = [
                'leave_email' => $employee->email,
                'leave_status_name' => $employee->name,
                'leave_status' => $request->status,
                'leave_reason' => $leave->leave_reason,
                'leave_start_date' => $leave->start_date,
                'leave_end_date' => $leave->end_date,
                'total_leave_days' => $leave->total_leave_days,

            ];
            $resp = Utility::sendEmailTemplate('leave_status', [$employee->email], $uArr);
            return redirect()->route('leave.index')->with('success', __('Leave status successfully updated.') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
        }

        return redirect()->route('leave.index')->with('success', __('Leave status successfully updated.'));
    }

    public function jsoncount(Request $request)
    {
        $date = Utility::AnnualLeaveCycle();
        $leave_counts = LeaveType::select(\DB::raw('COALESCE(SUM(leaves.total_leave_days),0) AS total_leave, leave_types.title, leave_types.days,leave_types.id'))
            ->leftjoin(
                'leaves',
                function ($join) use ($request, $date) {
                    $join->on('leaves.leave_type_id', '=', 'leave_types.id');
                    $join->where('leaves.employee_id', '=', $request->employee_id);
                    $join->where('leaves.status', '=', 'Approved');
                    $join->whereBetween('leaves.created_at', [$date['start_date'], $date['end_date']]);
                }
            )->where('leave_types.created_by', '=', \Auth::user()->creatorId())->groupBy('leave_types.id')->get();
        return $leave_counts;
    }

    public function calender(Request $request)
    {
        $created_by = \Auth::user()->creatorId();
        $Meetings = LocalLeave::where('created_by', $created_by)->get();

        $today_date = date('m');
        $current_month_event = LocalLeave::select('id', 'start_date', 'employee_id', 'created_at')->whereRaw('MONTH(start_date)=' . $today_date)->get();

        $arrMeeting = [];

        foreach ($Meetings as $meeting) {
            $arr['id']        = $meeting['id'];
            $arr['employee_id']     = $meeting['employee_id'];
            // $arr['leave_type_id']     = date('Y-m-d', strtotime($meeting['start_date']));
        }

        $leaves = LocalLeave::where('created_by', '=', \Auth::user()->creatorId())->get();
        if (\Auth::user()->type == 'employee') {
            $user     = \Auth::user();
            $employee = Employee::where('user_id', '=', $user->id)->first();
            $leaves   = LocalLeave::where('employee_id', '=', $employee->id)->get();
        } else {
            $leaves = LocalLeave::where('created_by', '=', \Auth::user()->creatorId())->get();
        }

        return view('leave.calender', compact('leaves'));
    }

    public function get_leave_data(Request $request)
    {
        if (\Auth::user()->type == 'employee') {
        $user     = \Auth::user();
        $employee = Employee::where('user_id', '=', $user->id)->first();
        $data   = LocalLeave::where('employee_id', '=', $employee->id)->get();
    } else {
        $data = LocalLeave::where('created_by', \Auth::user()->creatorId())->get();
    }
        $arrayJson = [];
        if ($request->get('calender_type') == 'google_calender') {
            $type = 'leave';
            $arrayJson =  Utility::getCalendarData($type);
        } else {
            foreach ($data as $val) {
                $end_date = date_create($val->end_date);
                date_add($end_date, date_interval_create_from_date_string("1 days"));
                $arrayJson[] = [
                    "id" => $val->id,
                    "title" => !empty(\Auth::user()->getLeaveType($val->leave_type_id)) ? \Auth::user()->getLeaveType($val->leave_type_id)->title : '',
                    "start" => $val->start_date,
                    "end" => date_format($end_date, "Y-m-d H:i:s"),
                    "className" => $val->color,
                    "textColor" => '#FFF',
                    "allDay" => true,
                    "url" => route('leave.action', $val['id']),
                ];
            }
        }

        return $arrayJson;
    }
}
