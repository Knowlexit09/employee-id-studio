<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class StudioAuthController extends Controller
{
    public function loginForm() { return view('studio.auth.login'); }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email'=>['required','email'], 'password'=>['required','string']]);
        if (Auth::attempt([...$credentials, 'studio_active'=>true])) {
            $request->session()->regenerate();
            if (!$request->user()->studio_company_id) {
                Auth::logout(); $request->session()->invalidate();
                return back()->withErrors(['email'=>'Your account has no company.']);
            }
            return redirect()->intended(route('studio.dashboard'));
        }
        return back()->withErrors(['email'=>'Invalid account credentials.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('studio.login');
    }
}
