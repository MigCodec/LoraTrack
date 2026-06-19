<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'loratrack:create-admin {email? : Correo del administrador} {--name= : Nombre visible}';

    protected $description = 'Crea o actualiza el usuario administrador inicial de LoraTrack';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) ($this->argument('email') ?: $this->ask('Correo'))));
        $name = trim((string) ($this->option('name') ?: $this->ask('Nombre', 'Administrador')));
        $password = (string) $this->secret('Contraseña (mínimo 12 caracteres)');

        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => UserRole::Admin,
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        $this->info("Administrador {$email} disponible.");

        return self::SUCCESS;
    }
}
