<?php

namespace App\Http\Middleware;

use App\Models\Outlet;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan user yang login masih aktif dan punya outlet aktif di session.
 * Kalau tidak, sesi diakhiri dan user diarahkan ke halaman login.
 */
class EnsureValidPosSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if (!$user->is_active) {
            return $this->endSession($request, 'Akun Anda sudah dinonaktifkan. Silakan hubungi Owner.');
        }

        $outletId = $request->session()->get('current_outlet_id');

        if (!$outletId || !Outlet::whereKey($outletId)->exists()) {
            return $this->endSession($request, 'Sesi outlet tidak ditemukan. Silakan login dan pilih outlet kembali.');
        }

        return $next($request);
    }

    protected function endSession(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('error', $message);
    }
}
