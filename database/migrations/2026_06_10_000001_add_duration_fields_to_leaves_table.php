<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDurationFieldsToLeavesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasColumn('leaves', 'duration_type') || !Schema::hasColumn('leaves', 'half_day_type')) {
            Schema::table('leaves', function (Blueprint $table) {
                if (!Schema::hasColumn('leaves', 'duration_type')) {
                    $table->string('duration_type')->default('full_day')->after('end_date');
                }

                if (!Schema::hasColumn('leaves', 'half_day_type')) {
                    $table->string('half_day_type')->nullable()->after('duration_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (Schema::hasColumn('leaves', 'half_day_type') || Schema::hasColumn('leaves', 'duration_type')) {
            Schema::table('leaves', function (Blueprint $table) {
                if (Schema::hasColumn('leaves', 'half_day_type')) {
                    $table->dropColumn('half_day_type');
                }

                if (Schema::hasColumn('leaves', 'duration_type')) {
                    $table->dropColumn('duration_type');
                }
            });
        }
    }
}
