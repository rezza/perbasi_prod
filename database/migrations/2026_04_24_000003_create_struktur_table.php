<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('m_struktur', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama', 255);
            $table->string('jabatan', 255);
            $table->string('filename', 255)->nullable();
            $table->unsignedInteger('urutan')->default(1);
            $table->tinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('urutan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_struktur');
    }
};
