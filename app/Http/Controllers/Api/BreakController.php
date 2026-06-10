<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeBreak;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class BreakController extends Controller
{
    public function startBreak(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'date' => 'required|date',
            'break_start' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $employeeId = (int) $request->input('employee_id');
        $date = $request->input('date');
        $breakStart = $request->input('break_start');

        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid employee id.',
            ], 422);
        }

        $activeBreak = EmployeeBreak::where('employee_id', $employeeId)
            ->where('date', $date)
            ->where('status', 'active')
            ->first();

        if ($activeBreak) {
            return response()->json([
                'status' => false,
                'message' => 'You already have an active break. Please end it first.',
            ], 422);
        }

        $newBreak = EmployeeBreak::create([
            'employee_id' => $employeeId,
            'date' => $date,
            'break_start' => $breakStart,
            'status' => 'active',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Break started successfully.',
            'data' => $newBreak,
        ], 201);
    }

    public function endBreak(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'break_end' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $employeeId = (int) $request->input('employee_id');
        $breakEnd = $request->input('break_end');
        $todayDate = Carbon::now()->toDateString();

        $activeBreak = EmployeeBreak::where('employee_id', $employeeId)
            ->where('date', $todayDate)
            ->where('status', 'active')
            ->first();

        if (!$activeBreak) {
            return response()->json([
                'status' => false,
                'message' => 'No active break found.',
            ], 422);
        }

        $startTime = Carbon::parse($activeBreak->break_start);
        $endTime = Carbon::parse($breakEnd);
        $duration = $startTime->diffInMinutes($endTime);

        $activeBreak->update([
            'break_end' => $breakEnd,
            'duration' => $duration,
            'status' => 'completed',
        ]);

        return response()->json([
            'status' => true,
            'message' => "Break ended. Duration: {$duration} minutes.",
            'data' => [
                'break' => $activeBreak,
                'duration' => $duration,
                'formatted_duration' => $activeBreak->getFormattedDuration(),
            ],
        ]);
    }

    public function getBreakStatus(Request $request)
    {
        $employeeId = $request->input('employee_id');

        if (!$employeeId) {
            return response()->json([
                'status' => false,
                'message' => 'Employee ID is required.',
            ], 400);
        }

        $todayDate = Carbon::now()->toDateString();

        $activeBreak = EmployeeBreak::where('employee_id', $employeeId)
            ->where('date', $todayDate)
            ->where('status', 'active')
            ->first();

        return response()->json([
            'status' => true,
            'data' => [
                'is_on_break' => $activeBreak !== null,
                'active_break' => $activeBreak,
            ],
        ]);
    }

    public function getBreakList(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $date = $request->input('date');

        if (!$employeeId) {
            return response()->json([
                'status' => false,
                'message' => 'Employee ID is required.',
            ], 400);
        }

        $query = EmployeeBreak::where('employee_id', $employeeId);

        if ($date) {
            $query->where('date', $date);
        }

        $breaksList = $query->orderBy('date', 'desc')
            ->orderBy('break_start', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'breaks' => $breaksList,
                'total_breaks' => $breaksList->count(),
                'total_duration' => $breaksList->sum('duration'),
            ],
        ]);
    }
}