<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClientVisit;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ClientVisitApiController extends Controller
{
    /**
     * Save multiple client visits
     */
    public function saveVisits(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'visits' => 'required|array|min:1',
            'visits.*.client_name' => 'required|string|max:255',
            'visits.*.address' => 'required|string',
            'visits.*.time' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $employeeId = (int) $request->input('employee_id');
        $visits = $request->input('visits');

        // Verify employee exists
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid employee id.',
            ], 422);
        }

        $todayDate = Carbon::now()->toDateString();
        $savedVisits = [];

        DB::beginTransaction();
        try {
            foreach ($visits as $visitData) {
                $visit = ClientVisit::create([
                    'employee_id' => $employeeId,
                    'date' => $todayDate,
                    'client_name' => $visitData['client_name'],
                    'address' => $visitData['address'],
                    'time' => $visitData['time'],
                ]);
                $savedVisits[] = $visit;
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => count($savedVisits) . ' visit(s) saved successfully.',
                'data' => [
                    'visits' => $savedVisits,
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error saving visits: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get visit list for employee
     */
    public function getVisitList(Request $request)
    {
        $employeeId = $request->input('employee_id');

        if (!$employeeId) {
            return response()->json([
                'status' => false,
                'message' => 'Employee ID is required.',
            ], 400);
        }

        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid employee id.',
            ], 422);
        }

        $visits = ClientVisit::where('employee_id', $employeeId)
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'visits' => $visits,
            ],
        ]);
    }
}
