<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->char('sid', 12)->unique();//String id
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_sftp');
            $table->string('host');
            $table->string('username');
            $table->string('password')->nullable();
            $table->integer('port');
            $table->integer('timeout')->default(6);
            $table->boolean('log_actions')->default(1);
            $table->text('key')->default(null)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'host', 'is_sftp', 'username']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
