<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\BannerProgram;

class APIBannerProgramController extends Controller
{
    protected $bannerProgram;

    public function __construct()
    {
        $this->bannerProgram = new BannerProgram();
    }

    /**
     * 1. List Banner Program
     */
    public function getListBannerProgram(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        try {
            $list = $this->bannerProgram->getBannerProgramList();

            return ResponseController::sendSuccess([
                'data' => $list,
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 2. Detail Banner Program
     */
    public function getDetailBannerProgram(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            $detail = $this->bannerProgram->getDetailBannerProgram($id);
            if (!$detail) {
                return ResponseController::sendError(69);
            }

            return ResponseController::sendSuccess([
                'data' => $detail,
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Tambah Banner Program
     */
    public function addBannerProgram(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        if (empty($request->judul)) {
            return ResponseController::sendErrorData(98, ['field' => 'judul is required']);
        }
        if (empty($request->short_description)) {
            return ResponseController::sendErrorData(98, ['field' => 'short_description is required']);
        }

        $coverType = $request->cover_type;
        if (!in_array($coverType, ['image', 'youtube', 'icon'])) {
            return ResponseController::sendErrorData(98, ['field' => 'cover_type must be image, youtube, or icon']);
        }

        $coverError = $this->validateCoverFields($coverType, $request);
        if ($coverError) {
            return ResponseController::sendErrorData(98, ['field' => $coverError]);
        }

        if ($this->bannerProgram->countBannerProgram() >= 3) {
            return ResponseController::sendError(70);
        }

        try {
            $record = new BannerProgram([
                'judul'             => $request->judul,
                'short_description' => $request->short_description,
                'artikel'           => $request->artikel ?? '',
                'cover_type'        => $coverType,
                'image_url'         => $request->image_url ?? '',
                'youtube_url'       => $request->youtube_url ?? '',
                'icon_name'         => $request->icon_name ?? '',
                'external_link'     => $request->external_link ?? '',
                'id_admin'          => $request->id_admin ?? null,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
            $record->save();

            return ResponseController::sendSuccess([
                'message' => 'Data berhasil disimpan',
                'data'    => ['id' => md5($record->id . $this->bannerProgram->imbuhan)],
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Ubah Banner Program
     */
    public function editBannerProgram(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            $existing = $this->bannerProgram->getBannerProgramByEncId($id);
            if (!$existing) {
                return ResponseController::sendError(69);
            }

            $update = BannerProgram::find($existing->id);
            if (!$update) {
                return ResponseController::sendError(69);
            }

            if (!empty($request->judul)) {
                $update->judul = $request->judul;
            }
            if ($request->has('short_description') && !empty($request->short_description)) {
                $update->short_description = $request->short_description;
            }
            if ($request->has('artikel')) {
                $update->artikel = $request->artikel;
            }

            $coverType = $request->cover_type;
            if (!empty($coverType)) {
                if (!in_array($coverType, ['image', 'youtube', 'icon'])) {
                    return ResponseController::sendErrorData(98, ['field' => 'cover_type must be image, youtube, or icon']);
                }

                $coverError = $this->validateCoverFields($coverType, $request);
                if ($coverError) {
                    return ResponseController::sendErrorData(98, ['field' => $coverError]);
                }

                $update->cover_type  = $coverType;
                $update->image_url   = $request->image_url ?? '';
                $update->youtube_url = $request->youtube_url ?? '';
                $update->icon_name   = $request->icon_name ?? '';
            }

            if ($request->has('external_link')) {
                $update->external_link = $request->external_link;
            }
            if ($request->has('id_admin')) {
                $update->id_admin = $request->id_admin;
            }

            $update->save();

            return ResponseController::sendSuccess([
                'message' => 'Data berhasil diubah',
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 5. Hapus Banner Program
     */
    public function deleteBannerProgram(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            $existing = $this->bannerProgram->getBannerProgramByEncId($id);
            if (!$existing) {
                return ResponseController::sendError(69);
            }

            $delete = BannerProgram::find($existing->id);
            $delete->delete();

            return ResponseController::sendSuccess([
                'message' => 'Data berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 6. Upload Gambar Cover
     */
    public function uploadImageBannerProgram(Request $request)
    {
        $filename = $request->filename ?? 'noname';
        $filename = basename($filename);

        if (stripos($filename, 'blob:') === 0) {
            return ResponseController::sendErrorData(98, [
                'field' => 'filename should be a plain file name, got blob URL',
            ]);
        }

        $file = null;
        foreach (['uploaded_file', 'file', 'image'] as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                break;
            }
        }

        if (!$file) {
            return ResponseController::sendErrorData(98, [
                'field' => 'file (binary) is required (key: uploaded_file / file / image)',
            ]);
        }

        $targetDir = public_path('banner-program');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        try {
            $file->move($targetDir, $filename);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }

        return ResponseController::sendSuccess([
            'message'  => 'Upload berhasil',
            'data'     => ['filename' => $filename],
        ]);
    }

    private function validateCoverFields(string $coverType, Request $request): ?string
    {
        if ($coverType === 'image' && empty($request->image_url)) {
            return 'image_url is required when cover_type is image';
        }

        if ($coverType === 'youtube') {
            if (empty($request->youtube_url)) {
                return 'youtube_url is required when cover_type is youtube';
            }
            if (!preg_match('/(youtube\.com|youtu\.be)\//', $request->youtube_url)) {
                return 'youtube_url must be a valid YouTube URL';
            }
        }

        if ($coverType === 'icon') {
            if (empty($request->icon_name)) {
                return 'icon_name is required when cover_type is icon';
            }
            if (!in_array($request->icon_name, BannerProgram::$validIcons)) {
                return 'icon_name is not a valid Material icon name';
            }
        }

        return null;
    }
}
