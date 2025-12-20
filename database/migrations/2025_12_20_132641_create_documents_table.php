<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('binder_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('reference_number')->nullable();
            $table->string('issuer')->nullable();
            $table->string('category')->nullable();
            $table->date('document_date')->nullable();
            $table->date('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('tags')->nullable();
            $table->timestamps();

            $table->index(['binder_id', 'document_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
