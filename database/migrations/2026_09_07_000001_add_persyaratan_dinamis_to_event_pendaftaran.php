<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_master_persyaratan_event', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 50)->unique();
            $table->string('label', 255);
            $table->string('tipe_input', 20)->default('text'); // text|number|textarea|date|dropdown|upload
            $table->string('sumber', 20)->default('custom'); // profil|registrasi|custom
            $table->string('profil_field_map', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('urutan')->default(0);
            $table->string('status', 20)->default('aktif'); // aktif|nonaktif
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('status');
        });

        $now = date('Y-m-d H:i:s');
        DB::table('m_master_persyaratan_event')->insert([
            // Existing 6 (kept exactly as before, still auto-checked by default on new events)
            ['key' => 'nama', 'label' => 'Nama', 'tipe_input' => 'text', 'sumber' => 'profil', 'profil_field_map' => 'nama', 'is_default' => true, 'urutan' => 1, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'mitra_perkumpulan', 'label' => 'Mitra Perkumpulan', 'tipe_input' => 'dropdown', 'sumber' => 'registrasi', 'profil_field_map' => null, 'is_default' => true, 'urutan' => 2, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'nik', 'label' => 'NIK', 'tipe_input' => 'text', 'sumber' => 'profil', 'profil_field_map' => 'nik', 'is_default' => true, 'urutan' => 3, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'ktp', 'label' => 'KTP', 'tipe_input' => 'upload', 'sumber' => 'profil', 'profil_field_map' => 'filename_ktp', 'is_default' => true, 'urutan' => 4, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'kk', 'label' => 'KK', 'tipe_input' => 'upload', 'sumber' => 'registrasi', 'profil_field_map' => null, 'is_default' => true, 'urutan' => 5, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'foto_profil', 'label' => 'Foto Profil', 'tipe_input' => 'upload', 'sumber' => 'profil', 'profil_field_map' => 'filename_foto', 'is_default' => true, 'urutan' => 6, 'status' => 'aktif', 'created_at' => $now],
            // New: fields already collected on registrasi anggota / edit profil, now selectable as event requirements too
            ['key' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'tipe_input' => 'text', 'sumber' => 'profil', 'profil_field_map' => 'tempat_lahir', 'is_default' => false, 'urutan' => 7, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'tanggal_lahir', 'label' => 'Tanggal Lahir', 'tipe_input' => 'date', 'sumber' => 'profil', 'profil_field_map' => 'tanggal_lahir', 'is_default' => false, 'urutan' => 8, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'tipe_input' => 'dropdown', 'sumber' => 'profil', 'profil_field_map' => 'jenis_kelamin', 'is_default' => false, 'urutan' => 9, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'alamat', 'label' => 'Alamat Lengkap', 'tipe_input' => 'textarea', 'sumber' => 'profil', 'profil_field_map' => 'alamat', 'is_default' => false, 'urutan' => 10, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'no_hp', 'label' => 'Nomor Telepon', 'tipe_input' => 'text', 'sumber' => 'profil', 'profil_field_map' => 'no_hp', 'is_default' => false, 'urutan' => 11, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'email', 'label' => 'Email', 'tipe_input' => 'text', 'sumber' => 'profil', 'profil_field_map' => 'email', 'is_default' => false, 'urutan' => 12, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'no_darurat', 'label' => 'Kontak Darurat', 'tipe_input' => 'text', 'sumber' => 'profil', 'profil_field_map' => 'no_darurat', 'is_default' => false, 'urutan' => 13, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'hubungan_nodarurat', 'label' => 'Hubungan Kontak Darurat', 'tipe_input' => 'text', 'sumber' => 'profil', 'profil_field_map' => 'hubungan_nodarurat', 'is_default' => false, 'urutan' => 14, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'pekerjaan', 'label' => 'Pekerjaan', 'tipe_input' => 'text', 'sumber' => 'profil', 'profil_field_map' => 'pekerjaan', 'is_default' => false, 'urutan' => 15, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'no_pelajar', 'label' => 'Kartu Pelajar/Mahasiswa', 'tipe_input' => 'text', 'sumber' => 'profil', 'profil_field_map' => 'no_pelajar', 'is_default' => false, 'urutan' => 16, 'status' => 'aktif', 'created_at' => $now],
            ['key' => 'no_kk', 'label' => 'Nomor KK', 'tipe_input' => 'text', 'sumber' => 'profil', 'profil_field_map' => 'no_kk', 'is_default' => false, 'urutan' => 17, 'status' => 'aktif', 'created_at' => $now],
        ]);

        Schema::table('m_event_pendaftaran', function (Blueprint $table) {
            $table->string('tipe_penempatan', 20)->default('banner')->after('id_news'); // banner|berita
        });

        Schema::table('m_event_pendaftaran_peserta', function (Blueprint $table) {
            $table->text('data_persyaratan')->nullable()->after('id_perkumpulan'); // JSON: {"<key>": "<value atau filename>"}
            $table->tinyInteger('jumlah_pengajuan')->default(1)->after('status');
            $table->text('riwayat_penolakan')->nullable()->after('catatan'); // JSON array of previous {catatan, id_admin_approval, tgl_approval}
        });

        // required_fields on existing (dev-only) rows never had data beyond the 6 legacy keys,
        // which are seeded 1:1 above, so no backfill of m_event_pendaftaran is needed.

        Schema::table('m_event_pendaftaran_peserta', function (Blueprint $table) {
            $table->dropColumn(['nama', 'nik', 'filename_ktp', 'filename_kk', 'filename_foto_profil']);
        });
    }

    public function down(): void
    {
        Schema::table('m_event_pendaftaran_peserta', function (Blueprint $table) {
            $table->string('nama', 255)->nullable();
            $table->string('nik', 20)->nullable();
            $table->string('filename_ktp', 255)->nullable();
            $table->string('filename_kk', 255)->nullable();
            $table->string('filename_foto_profil', 255)->nullable();
            $table->dropColumn(['data_persyaratan', 'jumlah_pengajuan', 'riwayat_penolakan']);
        });

        Schema::table('m_event_pendaftaran', function (Blueprint $table) {
            $table->dropColumn('tipe_penempatan');
        });

        Schema::dropIfExists('m_master_persyaratan_event');
    }
};
