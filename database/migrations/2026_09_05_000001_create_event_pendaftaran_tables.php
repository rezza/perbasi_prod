<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_event_pendaftaran', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_news')->nullable();
            $table->string('nama_event', 255);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('lokasi', 255)->nullable();
            $table->decimal('biaya_pendaftaran', 12, 2)->nullable();
            $table->string('skema_pendaftaran', 30); // 'mitra_dan_anggota' | 'anggota'
            $table->text('required_fields'); // JSON array, e.g. ["nama","nik","ktp"]
            $table->string('status', 20)->default('draft'); // draft|aktif|selesai|nonaktif
            $table->unsignedBigInteger('id_admin')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('id_news');
            $table->index('status');
        });

        Schema::create('m_event_pendaftaran_perkumpulan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_event_pendaftaran');
            $table->unsignedBigInteger('id_perkumpulan');

            $table->index('id_event_pendaftaran');
            $table->index('id_perkumpulan');
        });

        Schema::create('m_event_pendaftaran_peserta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_event_pendaftaran');
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_perkumpulan')->nullable();
            $table->string('nama', 255)->nullable();
            $table->string('nik', 20)->nullable();
            $table->string('filename_ktp', 255)->nullable();
            $table->string('filename_kk', 255)->nullable();
            $table->string('filename_foto_profil', 255)->nullable();
            $table->tinyInteger('status')->default(0); // 0 menunggu, 1 diterima, -1 ditolak
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('id_admin_approval')->nullable();
            $table->timestamp('tgl_daftar')->useCurrent();
            $table->timestamp('tgl_approval')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('id_event_pendaftaran');
            $table->index('id_user');
            $table->index('id_perkumpulan');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_event_pendaftaran_peserta');
        Schema::dropIfExists('m_event_pendaftaran_perkumpulan');
        Schema::dropIfExists('m_event_pendaftaran');
    }
};
