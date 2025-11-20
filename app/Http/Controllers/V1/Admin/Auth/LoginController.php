<?php

namespace Crater\Http\Controllers\V1\Admin\Auth;

use Crater\Http\Controllers\Controller;
use Crater\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(\Illuminate\Http\Request $request, $user)
    {
        \Log::info('LoginController: User authenticated', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'guard' => 'web',
            'session_id' => $request->session()->getId(),
            'session_started' => $request->session()->isStarted(),
            'authenticated_web' => \Auth::guard('web')->check(),
            'authenticated_sanctum' => \Auth::guard('sanctum')->check(),
            'host' => $request->getHost(),
            'tenancy_initialized' => tenancy()->initialized,
        ]);

        // Для SPA возвращаем JSON вместо редиректа
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'user' => $user,
            ]);
        }

        return redirect()->intended($this->redirectPath());
    }
}
