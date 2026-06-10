<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Employee;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // fetch related employee by user_id (use employees.id, not users.id)
            $employee = Employee::where('user_id', $user->id)->first();

            $token = $user->createToken('API Token')->plainTextToken;

            // Prepare the user payload so that `id` is the employees.id (as you required)
            // If employee not found, fall back to users.id but still return employee fields as null.
            $employeeId = $employee ? $employee->id : null;
            $employeeName = $employee ? $employee->name : null;

            $userPayload = [
                'id' => $employeeId ?? $user->id, // primary requirement: id should be employees.id when available
                'email' => $user->email,
                'name' => $employeeName ?? $user->name, // prefer employee.name
            ];

            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'user' => $userPayload,
                    'employee_id' => $employeeId,
                    'employee_name' => $employeeName,
                ],
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid credentials',
        ], 401);
    }

    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['status' => true, 'message' => 'Logged out successfully']);
    }
}
