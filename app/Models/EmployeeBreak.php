<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBreak extends Model
{
    use HasFactory;

    protected $table = 'breaks';

    protected $fillable = [
        'employee_id',
        'date',
        'break_start',
        'break_end',
        'duration',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function calculateDuration()
    {
        if ($this->break_start && $this->break_end) {
            $start = \Carbon\Carbon::parse($this->break_start);
            $end = \Carbon\Carbon::parse($this->break_end);
           return $start->diffInMinutes($end);
        }
        return 0;
    }

    public function getFormattedDuration()
    {
        if (!$this->duration) {
            return '0 min';
        }
        
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        return $minutes . 'm';
    }
}
