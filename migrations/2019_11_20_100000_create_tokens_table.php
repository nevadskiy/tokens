<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(config('tokens.table'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('token');
            $table->string('name', 100);
            $table->morphs('tokenable');
            $table->timestamp('expired_at');
            $table->timestamp('used_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['token', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(config('tokens.table'));
    }
}
