<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The (incident_id, component_id) unique already serves lookups by
     * incident_id, so the single column index only costs writes and space.
     */
    public function up(): void
    {
        if (! Schema::hasIndex('incident_components', ['incident_id'])) {
            return;
        }

        Schema::table('incident_components', function (Blueprint $table) {
            $table->dropIndex(['incident_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incident_components', function (Blueprint $table) {
            $table->index('incident_id');
        });
    }
};
