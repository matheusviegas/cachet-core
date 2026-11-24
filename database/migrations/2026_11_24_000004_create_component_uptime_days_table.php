<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per component per day, so the status page reads uptime history
     * instead of recalculating it from the raw history on every visit.
     */
    public function up(): void
    {
        Schema::create('component_uptime_days', function (Blueprint $table) {
            $table->id();

            if ($this->componentsTableUsesBigIntegerIds()) {
                $table->unsignedBigInteger('component_id');
            } else {
                $table->unsignedInteger('component_id');
            }

            $table->date('date');
            $table->unsignedTinyInteger('status');
            $table->unsignedTinyInteger('severity');
            $table->unsignedInteger('available_seconds')->default(0);
            $table->unsignedInteger('operational_seconds')->default(0);
            $table->timestamps();

            $table->unique(['component_id', 'date']);
            $table->index('date');
            $table->foreign('component_id')->references('id')->on('components')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_uptime_days');
    }

    /**
     * Whether the components table keys its rows by big integers.
     *
     * See 2025_09_14_000000_create_component_checks_table: component_id must
     * mirror the parent column type for MySQL to accept the foreign key.
     */
    private function componentsTableUsesBigIntegerIds(): bool
    {
        return in_array(Schema::getColumnType('components', 'id'), ['bigint', 'int8'], true);
    }
};
