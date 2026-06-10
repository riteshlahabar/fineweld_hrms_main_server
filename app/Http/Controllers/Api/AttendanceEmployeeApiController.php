<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceEmployee;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Models\Utility;

class AttendanceEmployeeApiController extends Controller
{
    /**
     * Check-in API
     *
     * Request expected:
     *  - employee_id (required) -> must be employees.id (primary key)
     *  - date (optional, defaults to today)
     *  - clock_in (optional, defaults to current server time)
     *  - location_in (optional)
     *
     * Response: status, message, data (attendance + employee_name)
     */
    public function checkIn(Request $request)
    {
        $now = Carbon::now();

        // Build payload with server defaults when missing
        $payload = $request->all();
        if (!$request->has('date') || empty($request->input('date'))) {
            $payload['date'] = $now->toDateString();
        }
        if (!$request->has('clock_in') || empty($request->input('clock_in'))) {
            $payload['clock_in'] = $now->format('H:i:s');
        }

        // Validate that employee_id exists in employees.id (primary key)
        $validator = Validator::make($payload, [
            'employee_id' => 'required|integer|exists:employees,id',
            'date'        => 'required|date',
            'clock_in'    => 'required',
            'location_in' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $employeeId = (int) $payload['employee_id'];

        // Find employee by primary key (id)
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json([
                'status'  => false,
                'message' => 'The selected employee id is invalid.',
                'errors'  => ['employee_id' => ['The selected employee id is invalid.']],
            ], 422);
        }

        // Prevent double check-in for the same date
        $existing = AttendanceEmployee::where('employee_id', $employeeId)
            ->where('date', $payload['date'])
            ->first();

        if ($existing) {
            return response()->json([
                'status'  => false,
                'message' => 'Attendance already checked in for this date.',
            ], 409);
        }

        // ===== ADD THIS LATE CALCULATION BLOCK =====
$startTime = Utility::getValByName('company_start_time'); // Add this import at top if needed
$date = $payload['date'];
$expectedStartTime = $date . ' ' . $startTime;
$actualClockInTime = $date . ' ' . $payload['clock_in'];

$totalLateSeconds = strtotime($actualClockInTime) - strtotime($expectedStartTime);
$totalLateSeconds = max($totalLateSeconds, 0); // Ensure non-negative

$hours = floor($totalLateSeconds / 3600);
$mins = floor($totalLateSeconds / 60 % 60);
$secs = floor($totalLateSeconds % 60);
$lateTime = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
// ===== END LATE CALCULATION =====

        // Create attendance record (employee_id is FK referencing employees.id)
        $attendance = AttendanceEmployee::create([
            'employee_id'   => $employeeId,
            'date'          => $payload['date'],
            'clock_in'      => $payload['clock_in'],
            'location_in'   => $payload['location_in'] ?? null,
            'clock_out'     => '00:00:00',
            'status'        => 'Present',
            'late'          => $lateTime,
            'early_leaving' => '00:00:00',
            'overtime'      => '00:00:00',
            'total_rest'    => '00:00:00',
            
        ]);

        // Return attendance and employee name
        return response()->json([
            'status'  => true,
            'message' => 'Checked in successfully.',
            'data'    => [
                'attendance'    => $attendance,
                'employee_id'   => $employee->id,
                'employee_name' => $employee->name,
            ],
        ], 201);
    }

    /**
     * Check-out API
     *
     * Request expected:
     *  - employee_id (required) -> must be employees.id (primary key)
     *  - date (optional, defaults to today)
     *  - clock_out (optional, defaults to current server time)
     *  - location_out (optional)
     *
     * Response: status, message, data (attendance + employee_name)
     */
    public function checkOut(Request $request)
    {
        $now = Carbon::now();

        $payload = $request->all();
        if (!$request->has('date') || empty($request->input('date'))) {
            $payload['date'] = $now->toDateString();
        }
        if (!$request->has('clock_out') || empty($request->input('clock_out'))) {
            $payload['clock_out'] = $now->format('H:i:s');
        }

        $validator = Validator::make($payload, [
            'employee_id'  => 'required|integer|exists:employees,id',
            'date'         => 'required|date',
            'clock_out'    => 'required',
            'location_out' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $employeeId = (int) $payload['employee_id'];

        // Find employee by primary key (id)
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json([
                'status'  => false,
                'message' => 'The selected employee id is invalid.',
                'errors'  => ['employee_id' => ['The selected employee id is invalid.']],
            ], 422);
        }

        $attendance = AttendanceEmployee::where('employee_id', $employeeId)
            ->where('date', $payload['date'])
            ->first();

        if (!$attendance) {
            return response()->json([
                'status'  => false,
                'message' => 'No check-in record found for this date.',
            ], 404);
        }

        if ($attendance->clock_out && $attendance->clock_out != '00:00:00') {
            return response()->json([
                'status'  => false,
                'message' => 'Already checked out for this date.',
            ], 409);
        }

        $attendance->clock_out = $payload['clock_out'];

        if (!empty($payload['location_out'])) {
            $attendance->location_out = $payload['location_out'];
        }
        
        // ===== ADD THIS EARLY LEAVING CALCULATION BLOCK =====
$endTime = Utility::getValByName('company_end_time'); // Add this import at top if needed
$date = $payload['date'];
$expectedEndTime = $date . ' ' . $endTime;
$actualClockOutTime = $date . ' ' . $payload['clock_out'];

$totalEarlySeconds = strtotime($expectedEndTime) - strtotime($actualClockOutTime);
$totalEarlySeconds = max($totalEarlySeconds, 0); // Ensure non-negative

$hours = floor($totalEarlySeconds / 3600);
$mins = floor($totalEarlySeconds / 60 % 60);
$secs = floor($totalEarlySeconds % 60);
$earlyLeavingTime = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

// Overtime calculation (bonus)
$overtime = '00:00:00';
if (strtotime($actualClockOutTime) > strtotime($expectedEndTime)) {
    $totalOvertimeSeconds = strtotime($actualClockOutTime) - strtotime($expectedEndTime);
    $hours = floor($totalOvertimeSeconds / 3600);
    $mins = floor($totalOvertimeSeconds / 60 % 60);
    $secs = floor($totalOvertimeSeconds % 60);
    $overtime = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
}
// ===== END EARLY LEAVING CALCULATION =====

$attendance->early_leaving = $earlyLeavingTime;
$attendance->overtime = $overtime;



        $attendance->save();

        return response()->json([
            'status'  => true,
            'message' => 'Checked out successfully.',
            'data'    => [
                'attendance'    => $attendance,
                'employee_id'   => $employee->id,
                'employee_name' => $employee->name,
            ],
        ]);
    }

    /**
     * Attendance list for an employee (expects employee_id = employees.id)
     */
    public function attendanceList(Request $request)
    {
        $employeeId = $request->input('employee_id');

        if (!$employeeId) {
            return response()->json([
                'status'  => false,
                'message' => 'Employee ID is required.',
            ], 400);
        }

        // Validate existence
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid employee id.',
            ], 422);
        }

        $records = AttendanceEmployee::where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'status'  => true,
            'data'    => [
                'employee_id'   => $employee->id,
                'employee_name' => $employee->name,
                'records'       => $records,
            ],
        ]);
    }
    
    public function getStatus(Request $request)
{
    // Get employee_id from the request, normally from token or passed param
    $employeeId = $request->input('employee_id');
    
    if (!$employeeId) {
        return response()->json([
            'status'  => false,
            'message' => 'Employee ID is required.',
        ], 400);
    }

    // Validate employee exists
    $employee = Employee::find($employeeId);
    if (!$employee) {
        return response()->json([
            'status'  => false,
            'message' => 'Invalid employee id.',
        ], 422);
    }

    // Fetch today's attendance (or latest) for this employee
    $todayDate = Carbon::now()->toDateString();

    $attendance = AttendanceEmployee::where('employee_id', $employeeId)
        ->where('date', $todayDate)
        ->first();

    if (!$attendance) {
        // No attendance for today means checked out by default
        return response()->json([
            'status'  => true,
            'message' => 'No attendance record for today.',
            'data'    => [
                'status'        => 'checked_out',
                'clock_in'      => null,
                'clock_out'     => null,
                'location_in'   => null,
                'location_out'  => null,
                'created_at'    => null,
                'updated_at'    => null,
            ],
        ]);
    }

    $status = 'checked_out';
    if ($attendance->clock_in && $attendance->clock_in != '00:00:00' && 
       (!$attendance->clock_out || $attendance->clock_out == '00:00:00')) {
        $status = 'checked_in';
    }

    return response()->json([
        'status'  => true,
        'message' => 'Attendance status retrieved successfully.',
        'data'    => [
            'status'        => $status,
            'clock_in'      => $attendance->clock_in,
            'clock_out'     => $attendance->clock_out,
            'location_in'   => $attendance->location_in,
            'location_out'  => $attendance->location_out,
            'created_at'    => $attendance->created_at,
            'updated_at'    => $attendance->updated_at,
        ],
    ]);
}

}
