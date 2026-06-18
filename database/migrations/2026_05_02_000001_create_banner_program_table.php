<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_banner_program', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('judul', 255);
            $table->text('short_description');
            $table->longText('artikel')->nullable();
            $table->string('cover_type', 20);
            $table->string('image_url', 255)->nullable();
            $table->string('youtube_url', 500)->nullable();
            $table->string('icon_name', 100)->nullable();
            $table->string('external_link', 500)->nullable();
            $table->unsignedBigInteger('id_admin')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_banner_program');
    }
};
