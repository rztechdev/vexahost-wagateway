<?php

namespace App\Http\Controllers;

use App\Support\DocsRepository;
use Illuminate\Contracts\View\View;

/**
 * Dokumentasi sebagai halaman web. Terbuka untuk publik — isinya penjelasan
 * cara kerja dan cara memakai, bukan data pelanggan.
 */
class DocsController extends Controller
{
    public function index(): View
    {
        return view('docs.index', [
            'catalogue' => DocsRepository::catalogue(),
        ]);
    }

    public function show(string $slug): View
    {
        return view('docs.show', [
            'catalogue' => DocsRepository::catalogue(),
            'page' => DocsRepository::page($slug),
            'activeSlug' => $slug,
        ]);
    }
}
