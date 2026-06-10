<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'client_name',
        'address',
        'time',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
