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
        Schema::create('read_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id')->unique();
            $table->integer('last_line_read')->nullable()->default(null);
            $table->integer('total_lines')->nullable()->default(null);
            $table->timestamps();
            $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('read_files');
    }
};
