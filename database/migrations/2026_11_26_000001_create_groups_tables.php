<?php

use Cachet\Enums\DefaultGroupEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the is_admin flag with permission groups: admins join the
     * Administrators group and everyone else joins the Read only group.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->string('name');
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();

            if ($this->usersTableUsesBigIntegerIds()) {
                $table->unsignedBigInteger('user_id');
            } else {
                $table->unsignedInteger('user_id');
            }

            $table->timestamps();

            $table->primary(['group_id', 'user_id']);
            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $now = now();

        $administratorsId = DB::table('groups')->insertGetId([
            'key' => DefaultGroupEnum::administrators->value,
            'name' => 'Administrators',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $readOnlyId = DB::table('groups')->insertGetId([
            'key' => DefaultGroupEnum::read_only->value,
            'name' => 'Read only',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        $this->addMembers($administratorsId, true, $now);
        $this->addMembers($readOnlyId, false, $now);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });

        DB::table('users')
            ->whereIn('id', DB::table('group_user')
                ->join('groups', 'groups.id', '=', 'group_user.group_id')
                ->where('groups.key', DefaultGroupEnum::administrators->value)
                ->select('group_user.user_id'))
            ->update(['is_admin' => true]);

        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }

    /**
     * Add every user with the given is_admin value to the group.
     */
    private function addMembers(int $groupId, bool $isAdmin, DateTimeInterface $now): void
    {
        DB::table('users')
            ->select('id')
            ->where('is_admin', $isAdmin)
            ->chunkById(500, fn ($users) => DB::table('group_user')->insert($users
                ->map(fn (object $user): array => [
                    'group_id' => $groupId,
                    'user_id' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()));
    }

    /**
     * Whether the users table keys its rows by big integers.
     *
     * user_id must mirror the parent column type for MySQL to accept the foreign key.
     */
    private function usersTableUsesBigIntegerIds(): bool
    {
        return in_array(Schema::getColumnType('users', 'id'), ['bigint', 'int8'], true);
    }
};
