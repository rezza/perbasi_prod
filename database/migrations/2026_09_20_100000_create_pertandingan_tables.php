<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_pertandingan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_news')->nullable();
            $table->unsignedBigInteger('id_event_pendaftaran'); // governing event, drives check-in eligibility
            $table->string('judul', 255); // free-text session title, e.g. "SMA N1 vs SMA N2" or "Seminar Peraturan Wasit"
            $table->dateTime('jam_mulai'); // date part always mirrors id_event_pendaftaran.tanggal_mulai
            $table->dateTime('jam_checkin'); // must be <= jam_mulai - 2 jam
            $table->boolean('checkin_enabled')->default(true);
            $table->unsignedBigInteger('id_admin')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('id_news');
            $table->index('id_event_pendaftaran');
        });

        Schema::create('m_pertandingan_checkin', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_pertandingan');
            $table->unsignedBigInteger('id_event_pendaftaran_peserta');
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_perkumpulan')->nullable(); // denormalized, for grouping
            $table->string('role', 10); // 'pemain' | 'sdm', resolved & frozen at submit time
            $table->string('posisi', 100)->nullable();
            $table->string('no_punggung', 10)->nullable(); // pemain only
            $table->tinyInteger('status_kehadiran'); // 1 hadir, 0 tidak hadir
            $table->timestamp('checkin_at')->useCurrent();

            $table->unique(['id_pertandingan', 'id_user']);
            $table->index('id_pertandingan');
            $table->index('id_perkumpulan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_pertandingan_checkin');
        Schema::dropIfExists('m_pertandingan');
    }
};
