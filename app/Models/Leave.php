<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $fillable = [
        'employee_id',
        'Leave_type_id',
        'applied_on',
        'start_date',
        'end_date',
        'duration_type',
        'half_day_type',
        'total_leave_days',
        'leave_reason',
        'remark',
        'status',
        'created_by',
    ];

    public function getLeaveDurationLabelAttribute(): string
    {
        if ($this->duration_type === 'half_day') {
            return $this->half_day_type === 'second_half' ? 'Half Day (Second Half)' : 'Half Day (First Half)';
        }

        return 'Full Day';
    }

    public function getFormattedTotalLeaveDaysAttribute(): string
    {
        if (is_numeric($this->total_leave_days)) {
            $formatted = number_format((float) $this->total_leave_days, 1, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        }

        return (string) $this->total_leave_days;
    }

    public function leaveType()
    {
        return $this->hasOne('App\Models\LeaveType', 'id', 'leave_type_id');
    }

    public function employees()
    {
        return $this->hasOne('App\Models\Employee', 'id', 'employee_id');
    }
}
