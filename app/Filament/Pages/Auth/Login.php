<?php

namespace App\Filament\Pages\Auth;

use Filament\Http\Livewire\Auth\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /**
     * Get the guard to use for authentication.
     *
     * @return string
     */
    protected function getGuard(): string
    {
        return 'admin';
    }

    /**
     * Get the user model to use for authentication.
     *
     * @return string
     */
    protected function getUserModel(): string
    {
        return \App\Models\AdminUser::class;
    }

    /**
     * Attempt to authenticate the user.
     *
     * @param  array  $data
     * @return Authenticatable
     *
     * @throws ValidationException
     */
    protected function attemptLogin(array $data): Authenticatable
    {
        $guard = $this->getGuard();
        $userModel = $this->getUserModel();

        // Устанавливаем search_path для текущей сессии перед поиском пользователя
        \Illuminate\Support\Facades\DB::statement('SET search_path TO admin');

        // Логирование для диагностики
        \Log::info('Filament Login: Attempting authentication', [
            'email' => $data['email'],
            'guard' => $guard,
            'user_model' => $userModel,
            'search_path' => \Illuminate\Support\Facades\DB::selectOne("SHOW search_path")->search_path ?? 'unknown',
        ]);

        $user = $userModel::where('email', $data['email'])->first();

        \Log::info('Filament Login: User lookup result', [
            'email' => $data['email'],
            'user_found' => $user !== null,
            'user_id' => $user ? $user->id : null,
            'password_hash_preview' => $user ? substr($user->password, 0, 20) . '...' : null,
        ]);

        if (!$user) {
            \Log::warning('Filament Login: User not found', [
                'email' => $data['email'],
            ]);
            throw ValidationException::withMessages([
                'email' => [__('filament::login.messages.failed')],
            ]);
        }

        // Проверяем пароль
        $passwordMatches = \Hash::check($data['password'], $user->password);
        
        \Log::info('Filament Login: Password check result', [
            'email' => $data['email'],
            'password_matches' => $passwordMatches,
            'password_length' => strlen($data['password']),
        ]);

        if (!$passwordMatches) {
            \Log::warning('Filament Login: Password mismatch', [
                'email' => $data['email'],
            ]);
            throw ValidationException::withMessages([
                'email' => [__('filament::login.messages.failed')],
            ]);
        }

        // Авторизуем пользователя
        \Auth::guard($guard)->login($user, $data['remember'] ?? false);

        \Log::info('Filament Login: Authentication successful', [
            'email' => $data['email'],
            'user_id' => $user->id,
            'session_id' => \Illuminate\Support\Facades\Session::getId(),
        ]);

        return $user;
    }
}

