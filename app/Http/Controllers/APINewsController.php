<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\News;
use App\Admin;

class APINewsController extends Controller
{
    protected $news;
    protected $admin;

    public function __construct()
    {
        $this->news = new News();
        $this->admin = new Admin();
    }

    /**
     * 1. Add News
     */
    public function addNews(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        // Validate required fields
        $required = ['judul', 'kategori', 'tanggal', 'konten', 'id_admin'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $judul = $request->judul;
        $kategori = $request->kategori;
        $tanggal = $request->tanggal;
        $konten = $request->konten;
        $id_user = $request->id_admin;
        $filename = $request->filename ?? null;

        try {
            // Validate kategori (1 = news, 2 = jadwal pertandingan)
            if (!in_array($kategori, [1, 2])) {
                return ResponseController::sendError(46); // Kategori tidak valid
            }

            // Validate admin exists
            $admin = $this->admin->getAdminByUserEncId($id_user);
            if (!$admin) {
                return ResponseController::sendError(47); // Admin tidak ditemukan
            }

            // Create news
            $news = new News([
                'judul' => $judul,
                'kategori' => $kategori,
                'tanggal' => $tanggal,
                'konten' => $konten,
                'id_admin' => $admin->id,
                'filename' => $filename,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $news->save();

            return ResponseController::sendSuccess([
                'message' => 'News berhasil ditambahkan',
                'data' => [
                    'id' => $news->id,
                    'id_enc' => md5($news->id . $this->news->imbuhan),
                    'judul' => $judul,
                    'kategori' => $kategori,
                    'tanggal' => $tanggal
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 2. Get List News
     * Menampilkan list news & jadwal aktif dengan data filename, id, judul, tanggal
     */
    public function getListNews(Request $request)
    {
        // Validate JWT Token
        // $validation = GlobalHelperController::validateJWT($request);
        // if (!$validation['valid']) {
        //     return GlobalHelperController::sendUnauthorized($validation['message']);
        // }

        $keyword = $request->keyword;
        $kategori = $request->kategori; // optional: 1 = news, 2 = jadwal
        $status = $request->status ?? 1; // default: active only

        try {
            // Get news list with selected fields
            $filter = 'n.status = ?';
            $param = [$status];

            if (!empty($keyword)) {
                $keyword = '%' . $keyword . '%';
                $filter .= ' AND (n.judul LIKE ? OR n.konten LIKE ?)';
                $param[] = $keyword;
                $param[] = $keyword;
            }

            if (!empty($kategori)) {
                $filter .= ' AND n.kategori = ?';
                $param[] = $kategori;
            }

            $newsList = DB::select(
                'SELECT 
                    md5(concat(n.id, "'.$this->news->imbuhan.'")) AS id,
                    n.filename,
                    n.judul,
                    n.tanggal,
                    n.kategori,
                    n.created_at,
                    a.username as admin_username,
                    n.is_banner
                FROM m_news n
                LEFT JOIN m_admin a ON n.id_admin = a.id
                WHERE '.$filter.'
                ORDER BY n.created_at DESC
                ', $param
            );

            $newsList = (new \App\Pertandingan())->attachSummaryToNewsRows($newsList);

            $paged = GlobalHelperController::paginate($newsList, $request);

            return ResponseController::sendSuccess([
                'message' => 'Data news berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function getListBanner(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
 
        try {
            // Get banner news
            $bannerNews = DB::select(
                'SELECT 
                    md5(concat(n.id, "'.$this->news->imbuhan.'")) AS id,
                    n.filename,
                    n.judul,
                    n.tanggal,
                    n.kategori,
                    n.created_at,
                    a.username as admin_username,
                    n.is_banner
                FROM m_news n
                LEFT JOIN m_admin a ON n.id_admin = a.id
                WHERE n.is_banner = 1 AND n.status = 1
                ORDER BY n.created_at DESC
                '
            );

            $paged = GlobalHelperController::paginate($bannerNews, $request);

            return ResponseController::sendSuccess([
                'message' => 'Data banner news berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Get Detail News
     * Menampilkan semua informasi news
     */
    public function getDetailNews(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            // Get news detail with all information
            $news = $this->news->getNewsDetails($id);
            if (!$news) {
                return ResponseController::sendError(48); // News tidak ditemukan
            }
            // Client-facing id must be the encoded one (what edit/delete expect);
            // the raw PK stays internal (compro web still reads it).
            $news->id = $news->id_news;
            return ResponseController::sendSuccess([
                'message' => 'Detail news berhasil diambil',
                'data' => $news
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Edit News
     */
    public function editNews(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $judul = $request->judul;
        $kategori = $request->kategori;
        $tanggal = $request->tanggal;
        $konten = $request->konten;
        $id_admin = $request->id_admin;
        $filename = $request->filename;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            // Get news
            $news = $this->news->getNewsByEncId($id);
            if (!$news) {
                return ResponseController::sendError(48); // News tidak ditemukan
            }

            // Find news model
            $updateNews = News::find($news->id);
            if (!$updateNews) {
                return ResponseController::sendError(48);
            }

            // Update fields if provided
            if (!empty($judul)) {
                $updateNews->judul = $judul;
            }

            if (!empty($kategori)) {
                // Validate kategori
                if (!in_array($kategori, [1, 2])) {
                    return ResponseController::sendError(46);
                }
                $updateNews->kategori = $kategori;
            }

            if (!empty($tanggal)) {
                $updateNews->tanggal = $tanggal;
            }

            if (!empty($konten)) {
                $updateNews->konten = $konten;
            }

            if (!empty($id_admin)) {
                $admin = $this->admin->getAdminByUserEncId($id_admin);
                if (!$admin) {
                    return ResponseController::sendError(47);
                }
                $updateNews->id_admin = $admin->id;
            }

            if (isset($filename)) {
                $updateNews->filename = $filename;
            }

            $updateNews->save();

            return ResponseController::sendSuccess([
                'message' => 'Data news berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function updateStatusBanner(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $is_banner = $request->is_banner;

        if (empty($id) || !isset($is_banner)) {
            return ResponseController::sendError(98);
        }

        try {
            // Get news
            $news = $this->news->getNewsByEncId($id);
            if (!$news) {
                return ResponseController::sendError(48); // News tidak ditemukan
            }

            // Update is_banner field
            $updateNews = News::find($news->id);
            $updateNews->is_banner = $is_banner;
            $updateNews->save();

            return ResponseController::sendSuccess([
                'message' => 'Status banner news berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 5. Delete News (Soft Delete)
     */
    public function deleteNews(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            // Get news
            $news = $this->news->getNewsByEncId($id);
            if (!$news) {
                return ResponseController::sendError(48); // News tidak ditemukan
            }

            // Soft delete
            $deleteNews = News::find($news->id);
            $deleteNews->status = -1;
            $deleteNews->save();

            return ResponseController::sendSuccess([
                'message' => 'Data news berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function uploadImageNews(Request $request){

        $filename = 'noname';

        if($request->filename){
            $filename = $request->filename;
        }
 
        if($request->hasfile('uploaded_file')){
            $file = $request->file('uploaded_file');
            $file->move('news', $filename); 
        }
        
        return ResponseController::sendSuccess([
            'message' => 'success', 
        ]); 
    }

}
