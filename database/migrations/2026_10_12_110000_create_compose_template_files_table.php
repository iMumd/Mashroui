<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مرفقات القالب الدائم — تُرفق تلقائيًا مع الإرسال عند استخدام القالب
        Schema::create('compose_template_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compose_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compose_template_files');
    }
};
