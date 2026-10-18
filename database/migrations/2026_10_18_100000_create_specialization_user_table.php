<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'specialization_id']);
        });

        DB::table('users')
            ->whereNotNull('specialization_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $now = now();
                $pivot = $rows->map(fn ($u) => [
                    'user_id' => $u->id,
                    'specialization_id' => $u->specialization_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                DB::table('specialization_user')->insertOrIgnore($pivot);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialization_user');
    }
};
