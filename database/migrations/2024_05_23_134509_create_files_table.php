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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->char('sid', 12)->unique();//String id
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('connection_id');
            $table->integer('size_kb')->nullable()->default(null);
            $table->string('ext')->nullable()->default(null);
            $table->string('saved_to')->nullable()->default(null);
            $table->string('saved_as')->nullable()->default(null);
            $table->string('original_dir')->nullable()->default(null);
            $table->string('original_name')->nullable()->default(null);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('connection_id')->references('id')->on('connections')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
