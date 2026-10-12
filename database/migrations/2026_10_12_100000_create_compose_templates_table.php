<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قوالب دائمة لنوافذ الإرسال (إعلان، ملف، تكليف) — مشتركة بين الإدارة العامة ولجنة الإشراف
        Schema::create('compose_templates', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['announcement', 'file', 'task']);
            $table->string('title', 200)->nullable();
            $table->text('body')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compose_templates');
    }
};
