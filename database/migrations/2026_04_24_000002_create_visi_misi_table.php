<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('m_visi_misi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tipe', 10); // 'visi' or 'misi'
            $table->string('judul', 255);
            $table->text('isi');
            $table->tinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_visi_misi');
    }
};
