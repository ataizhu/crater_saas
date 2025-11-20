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
            'session_domain' => config('session.domain'),
            'session_cookie_name' => config('session.cookie'),
        ]);

        // ВАЖНО: Принудительно сохраняем сессию, чтобы cookie установились
        // Это гарантирует, что StartSession middleware добавит cookie в ответ
        $request->session()->save();

        // Для SPA возвращаем JSON вместо редиректа
        $response = $request->expectsJson() 
            ? response()->json(['success' => true, 'user' => $user])
            : redirect()->intended($this->redirectPath());
        
        // После создания ответа проверяем, что cookie будут установлены
        // Laravel добавляет cookie через AddQueuedCookiesToResponse middleware
        // Мы не можем проверить их здесь, так как middleware еще не отработал
        
        return $response;
    }
}
