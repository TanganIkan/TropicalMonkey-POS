<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login dua langkah: (1) cek username/password, (2) pilih outlet.
 * User baru benar-benar login setelah outlet dipilih, sehingga tidak ada
 * sesi login tanpa outlet aktif.
 */
class LoginService
{
    public const PENDING_KEY = 'login.pending_user_id';

    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function __construct(
        private ActivityLogger $logger,
        private ShiftService $shifts,
    ) {}

    /**
     * Langkah 1. User belum login; ID-nya hanya disimpan di session server.
     *
     * @throws ValidationException
     */
    public function verifyCredentials(string $username, string $password, string $ip): User
    {
        $key = $this->throttleKey($username, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'username' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials(['username' => $username]);

        if (!$user || !$provider->validateCredentials($user, ['password' => $password])) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'username' => 'Username atau Password tidak valid.',
            ]);
        }

        RateLimiter::clear($key);

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => 'Akun Anda sudah dinonaktifkan. Silakan hubungi Owner.',
            ]);
        }

        session([self::PENDING_KEY => $user->id]);

        return $user;
    }

    public function pendingUser(): ?User
    {
        $id = session(self::PENDING_KEY);

        return $id ? User::where('is_active', true)->find($id) : null;
    }

    /**
     * Langkah 2. Login sungguhan + simpan outlet aktif.
     *
     * @throws ValidationException
     */
    public function complete(int $outletId, bool $remember = false): User
    {
        $user = $this->pendingUser();

        if (!$user) {
            throw ValidationException::withMessages([
                'selected_outlet_id' => 'Sesi login sudah kedaluwarsa. Silakan masukkan username dan password lagi.',
            ]);
        }

        Auth::login($user, $remember);
        session()->regenerate();
        session()->forget(self::PENDING_KEY);
        session(['current_outlet_id' => $outletId]);

        $this->logger->log('login', 'Login ke ' . (Outlet::find($outletId)?->name ?? 'outlet'), userId: $user->id, outletId: $outletId);

        // Staff langsung bertugas; owner baru membuka shift saat memakai POS
        if (!$user->isOwner()) {
            $this->shifts->ensureOpen($user, $outletId);
        }

        return $user;
    }

    protected function throttleKey(string $username, string $ip): string
    {
        return 'login:' . Str::lower(trim($username)) . '|' . $ip;
    }
}
