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
        Schema::create('bprof_traces', static function (Blueprint $table) {
            $table->uuid();
            $table->text('url')->index();
            $table->string('method');
            $table->integer('status_code')->nullable();
            $table->string('server_name');
            $table->boolean('ajax')->default(false);
            $table->longText('perfdata');
            $table->longText('cookie');
            $table->longText('post');
            $table->longText('get');
            $table->longText('headers');
            $table->integer('pmu');
            $table->integer('wt');
            $table->integer('cpu');
            $table->string('user_id')->nullable();
            $table->string('ip')->nullable();
            $table->integer('created_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bprof_traces');
    }
};
