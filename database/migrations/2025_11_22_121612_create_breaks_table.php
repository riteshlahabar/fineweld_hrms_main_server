<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('breaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('break_start');
            $table->time('break_end')->nullable();
            $table->integer('duration')->nullable()->comment('Duration in minutes');
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamps();

            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->onDelete('cascade');

            $table->index(['employee_id', 'date', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('breaks');
    }
};
