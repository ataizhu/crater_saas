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

        $user = $userModel::where('email', $data['email'])->first();

        if (!$user || !\Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('filament::login.messages.failed')],
            ]);
        }

        \Auth::guard($guard)->login($user, $data['remember'] ?? false);

        return $user;
    }
}

