<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdmin extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:make-admin
                            {email : 管理者のメールアドレス}
                            {--name= : 表示名（省略時はメールのローカル部）}
                            {--password= : パスワード（省略時は対話入力）}';

    /**
     * @var string
     */
    protected $description = 'システム管理者ユーザを作成（既存なら更新）する';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) ($this->option('name') ?: strstr($email, '@', true) ?: $email);
        $password = (string) ($this->option('password') ?: $this->secret('パスワードを入力してください'));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $adminRole = Role::firstOrCreate(
            ['code' => 'admin'],
            ['name' => 'システム管理者'],
        );

        $existed = User::where('email', $email)->exists();

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role_id' => $adminRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->info(sprintf(
            '%s 管理者ユーザ: %s (id=%d)',
            $existed ? '更新しました' : '作成しました',
            $user->email,
            $user->id,
        ));

        return self::SUCCESS;
    }
}
