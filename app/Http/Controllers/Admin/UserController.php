<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));

        return view('admin.users', [
            'users' => User::query()
                ->withCount('workspaces')
                ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                    $q->where('name', 'like', "%{$cari}%")
                        ->orWhere('email', 'like', "%{$cari}%");
                }))
                ->orderByDesc('is_super_admin')
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'cari' => $cari,
        ]);
    }

    /**
     * Memberi atau mencabut hak super admin.
     *
     * Mencabut hak diri sendiri ditolak. Bukan karena tidak boleh ada yang
     * mundur, tapi karena satu-satunya jalan masuk ke panel ini adalah lewat
     * super admin — orang terakhir yang mencabut haknya sendiri mengunci pintu
     * dari luar, dan memperbaikinya hanya bisa lewat akses langsung ke
     * database produksi.
     */
    public function toggleSuperAdmin(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->withErrors([
                'pengguna' => 'Hak super admin diri sendiri tidak bisa dicabut dari sini. Minta super admin lain yang melakukannya.',
            ]);
        }

        $user->forceFill(['is_super_admin' => ! $user->is_super_admin])->save();

        AuditLog::record('admin.super_admin_toggled', $user, [
            'email' => $user->email,
            'menjadi' => $user->is_super_admin,
            'oleh' => $request->user()->email,
        ]);

        return back()->with('status', $user->is_super_admin
            ? "{$user->email} sekarang super admin."
            : "Hak super admin {$user->email} dicabut.");
    }
}
