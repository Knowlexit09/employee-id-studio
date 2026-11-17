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
            if (!$request->user()->studio_company_id || !$request->user()->canViewStudio()) {
                Auth::logout(); $request->session()->invalidate();
                return back()->withErrors(['email'=>'Your account is not authorized to access this studio.']);
            }
            $request->session()->forget(['studio_mfa_verified_user_id','studio_mfa_enrollment_fingerprint','studio_mfa_pending_secret','studio_mfa_recovery_codes']);
            return redirect()->route($request->user()->studio_mfa_enabled_at
                ? 'studio.mfa.challenge' : 'studio.mfa.setup');
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
