<?php

namespace App\Http\Controllers;

class ResponseController {


    public static function sendSuccess($data){
        $res['status']  = '00';
        $res['message'] = 'Sukses';
        $res = array_merge($res, $data);
        return json_encode($res, true); 
    }

    public static function sendError($code){
        $res['status']  = $code;
        $res['message'] = ResponseController::getErrorMessage($code);
        return json_encode($res, true); 
    }

    public static function sendErrorData($code, $data){
        $res['status']  = $code;
        $res['message'] = ResponseController::getErrorMessage($code);
        $res['data'] = $data;
        return json_encode($res, true); 
    }

    public static function getErrorMessage($code){
        switch ($code) {
            case 1:
                return 'User tidak dikenali';
            case 3:
                return 'User sudah terdaftar dengan email atau nomor hp yang sama';
            case 4:
                return 'Perkumpulan tidak ditemukan';
            case 11:
                return 'Username atau Password salah';
            case 12:
                return 'User tidak terdaftar';
            case 13:
                return 'Pelanggan tidak terdaftar';
            case 14:
                return 'Password lama tidak sesuai';
            case 20:
                return 'Akun user tidak aktif, harap hubungi admin';
            case 21: 
                return 'User bukan admin';
            case 34:
                return 'Role tidak valid';
            case 35:
                return 'Username sudah terdaftar';
            case 36:
                return 'Kota tidak ditemukan';
            case 37:
                return 'No Pengkot sudah terdaftar';
            case 38:
                return 'Entitas masih memiliki perkumpulan aktif';
            case 39:
                return 'Entitas tidak ditemukan';
            case 40:
                return 'Status anggota tidak ditemukan';
            case 41:
                return 'No Perkumpulan sudah terdaftar';
            case 42:
                return 'Perkumpulan masih memiliki anggota aktif';
            case 43:
                return 'Nama posisi sudah terdaftar';
            case 44:
                return 'Posisi tidak ditemukan';
            case 45:
                return 'Posisi masih memiliki user aktif';
            case 46:
                return 'Kategori tidak valid';
            case 47:
                return 'Admin tidak ditemukan';
            case 48:
                return 'News tidak ditemukan';
            case 49:
                return 'Banner tidak ditemukan';
            case 50:
                return 'Onboarding tidak ditemukan';
            case 51:
                return 'Sudah ada pengajuan aktif untuk tahun ini';
            case 52:
                return 'Pengajuan tidak ditemukan';
            case 53:
                return 'Pengajuan sudah diproses sebelumnya';
            case 54:
                return 'Tipe pengajuan tidak valid';
            case 55:
                return 'Pengajuan belum disetujui oleh perkumpulan';
            case 56:
                return 'Email sudah digunakan oleh user lain';
            case 57:
                return 'Nomor HP sudah digunakan oleh user lain';
            case 58:
                return 'NIK sudah digunakan oleh user lain';
            case 59:
                return 'Format tanggal lahir tidak valid (YYYY-MM-DD)';
            case 60:
                return 'Jenis kelamin tidak valid (L/P)';
            case 61:
                return 'Jenjang pendidikan tidak valid';
            case 62:
                return 'Member dengan nomor anggota tersebut tidak ditemukan';
            case 63:
                return 'Format email tidak valid';
            case 64:
                return 'Email tidak terdaftar atau akun tidak aktif';
            case 65:
                return 'Kode OTP tidak valid atau sudah expired';
            case 66:
                return 'Password minimal 6 karakter';
            case 67:
                return 'Perkumpulan ini sudah memiliki admin';
            case 68:
                return 'User sudah terdaftar sebagai admin';
            case 69:
                return 'Banner program tidak ditemukan';
            case 70:
                return 'Maksimal 3 banner program. Hapus salah satu terlebih dahulu.';
            case 71:
                return 'Data ini terelasi dengan data lain';
            case 97:
                return 'Data tidak ditemukan';
            case 98:
                return 'Parameter tidak lengkap';
            case 99:
                return 'Terjadi kesalahan sistem';

            default:
                return 'Error Unknown';
        }
    }
    
}