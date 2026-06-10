<?php

namespace App\Http\Controllers;

use App\Models\Utility;
use Illuminate\Http\Request;

use Maatwebsite\Excel\Facades\Excel;
use App\Models\Employee;
use App\Models\Allowance;
use App\Models\Commission;
use App\Models\Loan;
use App\Models\SaturationDeduction;
use App\Models\OtherPayment;
use App\Models\Overtime;
use App\Models\AllowanceOption;
use App\Models\LoanOption;
use App\Models\DeductionOption;


class ImportController extends Controller
{
    public function getTableWiseFields($table)
    {
        $error = '';
        switch ($table) {
            case 'attendance_employees':
                $extraFields = ['id', 'status', 'late', 'early_leaving', 'overtime', 'total_rest', 'created_by', 'created_at', 'updated_at'];
                $tableFields = Utility::getTableFields($table, $extraFields);
                if ($tableFields['status']) {
                    if (($key = array_search('employee_id', $tableFields['data'])) !== false) {
                        $tableFields['data'][$key] = 'employee_email';
                    }
                    $route = "attendance.import.data";
                }
                break;
            case 'time_sheets':
                $extraFields = ['id', 'created_by', 'created_at', 'updated_at'];
                $tableFields = Utility::getTableFields($table, $extraFields);
                if ($tableFields['status']) {
                    if (($key = array_search('employee_id', $tableFields['data'])) !== false) {
                        $tableFields['data'][$key] = 'employee_email';
                    }
                    $route = "timesheet.import.data";
                }
                break;
            case 'holidays':
                $extraFields = ['id', 'created_by', 'created_at', 'updated_at'];
                $tableFields = Utility::getTableFields($table, $extraFields);
                if ($tableFields['status']) {
                    $desiredOrder = ['occasion', 'start_date', 'end_date'];
                    $tableFields['data'] = array_values(array_intersect($desiredOrder, $tableFields['data']));
                    $route = "holidays.import.data";
                }
                break;
            case 'assets':
                $extraFields = ['id', 'created_by', 'created_at', 'updated_at'];
                $tableFields = Utility::getTableFields($table, $extraFields);
                if ($tableFields['status']) {
                    if (($key = array_search('employee_id', $tableFields['data'])) !== false) {
                        $tableFields['data'][$key] = 'employee_email';
                    }
                    $route = "assets.import.data";
                }
                break;

                //==========================================================

            default:
                $error = 'Something went wrong!';
                $tableFields['status'] = false;
                break;
        }

        if ($tableFields['status']) {
            $fields = $tableFields['data'];
        } else {
            $error = $tableFields['message'];
        }
        return [
            'route' => $route,
            'fields' => $fields,
            'error' => $error,
        ];
    }

    public function fileImportModal(Request $request)
    {
        $fields = [];
        $route  = '';
        $tableFields = $this->getTableWiseFields($request->table);
        if ($tableFields['error'] != '') {
            $error = $tableFields['error'];
        } else {
            $fields = json_encode($tableFields['fields']);
            $route = $tableFields['route'];
        }

        return view('import.import_modal', compact('fields', 'route'));
    }

    public function fileImport(Request $request)
    {
        session_start();

        $error = '';

        $html = '';

        $fields = [];
        $route = '';

        if ($request->hasFile('file') && $request->file->getClientOriginalName() != '') {
            $file_array = explode(".", $request->file->getClientOriginalName());

            $extension = end($file_array);
            if ($extension == 'csv') {
                $file_data = fopen($request->file->getRealPath(), 'r');
                $file_header = fgetcsv($file_data);

                $tableFields = $this->getTableWiseFields($request->table);
                if ($tableFields['error'] != '') {
                    $error = $tableFields['error'];
                } else {
                    $fields = $tableFields['fields'];
                }

                $limit = 0;
                $temp_data = [];
                while (($row = fgetcsv($file_data)) !== false) {
                    $limit++;
                    $html .= '<tr>';
                    for ($count = 0; $count < count($row); $count++) {
                        $html .= '<td>' . $row[$count] . '</td>';
                    }
                    $html .= '</tr>';
                    $temp_data[] = $row;
                }

                $_SESSION['file_data'] = $temp_data;
            } else {
                $error = 'Only <b>.csv</b> file allowed';
            }
        } else {

            $error = 'Please Select CSV File';
        }
        $output = array(
            'error' => $error,
            'output' => $html,
            'fields' => $fields,
        );

        return json_encode($output);
    }
    

public function saveSalaryImport(Request $request)
{
    if (!$request->hasFile('file')) {
        return back()->with('error', 'Please upload file');
    }

    $data = Excel::toArray([], $request->file('file'))[0];

    if (count($data) <= 1) {
        return back()->with('error', 'Excel is empty');
    }

    foreach ($data as $key => $row) {

        if ($key == 0) continue; // skip header

        // =========================
        // EMPLOYEE
        // =========================
        $employee = Employee::where('email', trim($row[0]))->first();
        if (!$employee) continue;

        // =========================
        // BASIC SALARY
        // =========================
        if (!empty($row[1])) {
            $employee->salary = $row[1];
            $employee->save();
        }

        // =========================
        // ALLOWANCE
        // =========================
        if (!empty($row[4]) || !empty($row[5]) || !empty($row[6]) || !empty($row[7])) {

            if (empty($row[4]) || empty($row[5]) || empty($row[6]) || empty($row[7])) {
                return back()->with('error', 'Allowance fields missing in row ' . ($key + 1));
            }

            $option = AllowanceOption::where('name', $row[4])->first();

            Allowance::create([
                'employee_id' => $employee->id,
                'allowance_option' => $option ? $option->id : null,
                'title' => $row[5],
                'type' => $row[6],
                'amount' => $row[7],
                'created_by' => auth()->user()->creatorId()
            ]);
        }

        // =========================
        // COMMISSION
        // =========================
        if (!empty($row[8]) || !empty($row[9]) || !empty($row[10])) {

            if (empty($row[8]) || empty($row[9]) || empty($row[10])) {
                return back()->with('error', 'Commission fields missing in row ' . ($key + 1));
            }

            Commission::create([
                'employee_id' => $employee->id,
                'title' => $row[8],
                'type' => $row[9],
                'amount' => $row[10],
                'created_by' => auth()->user()->creatorId()
            ]);
        }

        // =========================
        // LOAN
        // =========================
        if (!empty($row[11]) || !empty($row[12]) || !empty($row[13]) || !empty($row[14]) || !empty($row[15])) {

            if (empty($row[11]) || empty($row[12]) || empty($row[13]) || empty($row[14])) {
                return back()->with('error', 'Loan fields missing in row ' . ($key + 1));
            }

            $option = LoanOption::where('name', $row[11])->first();

            Loan::create([
                'employee_id' => $employee->id,
                'loan_option' => $option ? $option->id : null,
                'title' => $row[12],
                'type' => $row[13],
                'amount' => $row[14],
                'reason' => $row[15] ?? '',
                'created_by' => auth()->user()->creatorId()
            ]);
        }

        // =========================
        // DEDUCTION
        // =========================
        if (!empty($row[16]) || !empty($row[17]) || !empty($row[18]) || !empty($row[19])) {

            if (empty($row[16]) || empty($row[17]) || empty($row[18])) {
                return back()->with('error', 'Deduction fields missing in row ' . ($key + 1));
            }

            $option = DeductionOption::where('name', $row[16])->first();

            SaturationDeduction::create([
                'employee_id' => $employee->id,
                'deduction_option' => $option ? $option->id : null,
                'title' => $row[17],
                'type' => $row[18],
                'amount' => $row[19],
                'created_by' => auth()->user()->creatorId()
            ]);
        }

        // =========================
        // OTHER PAYMENT
        // =========================
        if (!empty($row[20]) || !empty($row[21]) || !empty($row[22])) {

            if (empty($row[20]) || empty($row[21]) || empty($row[22])) {
                return back()->with('error', 'Other payment fields missing in row ' . ($key + 1));
            }

            OtherPayment::create([
                'employee_id' => $employee->id,
                'title' => $row[20],
                'type' => $row[21],
                'amount' => $row[22],
                'created_by' => auth()->user()->creatorId()
            ]);
        }

// =========================
// OVERTIME (FINAL WORKING FIX)
// =========================

$ot_title = trim($row[23] ?? '');
$ot_days  = trim($row[24] ?? '');
$ot_hours = trim($row[25] ?? '');
$ot_rate  = trim($row[26] ?? '');

// 👉 Treat "0" as empty for trigger
$isOvertimeFilled = ($ot_title !== '' || $ot_days > 0 || $ot_hours > 0 || $ot_rate > 0);

if ($isOvertimeFilled) {

    // 👉 Require all fields
    if ($ot_title === '' || $ot_days === '' || $ot_hours === '' || $ot_rate === '') {
        return back()->with('error', 'Overtime incomplete in row ' . ($key + 1));
    }

    // 👉 If all numeric values are 0 → skip
    if ((float)$ot_days == 0 && (float)$ot_hours == 0 && (float)$ot_rate == 0) {
        // skip silently
    } else {

        $amount = (float)$ot_days * (float)$ot_hours * (float)$ot_rate;

        \App\Models\Overtime::create([
            'employee_id' => $employee->id,
            'title' => $ot_title,
            'number_of_days' => $ot_days,
            'hours' => $ot_hours,
            'rate' => $ot_rate,
            'amount' => $amount,
            'created_by' => auth()->user()->creatorId()
        ]);
    }
}
    }

    return back()->with('success', 'Salary Imported Successfully');
}
}
