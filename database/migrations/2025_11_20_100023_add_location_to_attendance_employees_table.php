<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLocationToAttendanceEmployeesTable extends Migration
{
    public function up()
    {
        Schema::table('attendance_employees', function (Blueprint $table) {
            $table->string('location')->nullable()->after('overtime'); // Add location column after 'overtime'
        });
    }

    public function down()
    {
        Schema::table('attendance_employees', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
}
