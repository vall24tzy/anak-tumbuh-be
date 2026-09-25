<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AdminResetPassword extends Command
{
    protected $signature = 'admin:reset-password {username}';
    protected $description = 'Prosedur darurat: reset password Super Admin lewat CLI (bukan HTTP API)';

    public function handle(): int
    {
        $user = User::where('username', $this->argument('username'))
            ->where('role', 'super_admin')
            ->first();

        if (!$user) {
            $this->error('Super Admin dengan username tersebut tidak ditemukan.');
            return self::FAILURE;
        }

        $newPassword = $this->secret('Masukkan password baru (min 8 karakter)');

        if (strlen($newPassword) < 8) {
            $this->error('Password minimal 8 karakter.');
            return self::FAILURE;
        }

        $user->update(['password' => $newPassword]);
        $this->info("Password Super Admin '{$user->username}' berhasil direset.");

        return self::SUCCESS;
    }
}