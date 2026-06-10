<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAttendanceEmployeesTable extends Migration
{
    public function up()
    {
        Schema::table('attendance_employees', function (Blueprint $table) {
            // Drop old single location column
            if (Schema::hasColumn('attendance_employees', 'location')) {
                $table->dropColumn('location');
            }

            // Add location_in and location_out columns after total_rest column
            $table->string('location_in')->nullable()->after('total_rest');
            $table->string('location_out')->nullable()->after('location_in');
        });
    }

    public function down()
    {
        Schema::table('attendance_employees', function (Blueprint $table) {
            // Rollback: drop new location_in and location_out columns
            $table->dropColumn(['location_in', 'location_out']);

            // Add back the old location column
            if (!Schema::hasColumn('attendance_employees', 'location')) {
                $table->string('location')->nullable()->after('total_rest');
            }
        });
    }
}
