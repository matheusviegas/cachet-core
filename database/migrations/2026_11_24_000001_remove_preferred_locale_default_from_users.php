<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A null locale means "System default", so new users follow the browser
     * language instead of being pinned to English. Existing rows are kept,
     * since an explicit 'en' cannot be told apart from the old default.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_locale')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_locale')->nullable()->default('en')->change();
        });
    }
};
