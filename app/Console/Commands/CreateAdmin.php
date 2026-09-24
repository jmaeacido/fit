<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
class CreateAdmin extends Command
{
    protected $signature = 'admin:create';
    protected $description = 'Create an administrator (all application accounts are administrators)';
    public function handle(): int {
        $data = ['name' => $this->ask('Name'), 'username' => $this->ask('Username'), 'email' => $this->ask('Email (optional)') ?: null, 'password' => $this->secret('Password (at least 12 characters)')];
        $validator = Validator::make($data, ['name' => 'required|max:150', 'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', 'unique:users,username'], 'email' => 'nullable|email|unique:users,email', 'password' => 'required|min:12']);
        if ($validator->fails()) { foreach ($validator->errors()->all() as $error) $this->error($error); return self::FAILURE; }
        $data['password'] = Hash::make($data['password']);
        User::create($data);
        $this->info('Administrator created. Sign in at /login.');
        return self::SUCCESS;
    }
}
