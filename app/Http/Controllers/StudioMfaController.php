<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudioAudit;
use App\Services\StudioTotp;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudioMfaController extends Controller
{
    public function setup(Request $request, StudioTotp $totp)
    {
        $user = $request->user();
        abort_unless($user?->canViewStudio() && $user->studio_company_id, 403);
        if ($user->studio_mfa_enabled_at) {
            return redirect()->route('studio.mfa.challenge');
        }
        if (!$request->session()->has('studio_mfa_pending_secret')) {
            $request->session()->put('studio_mfa_pending_secret', Crypt::encryptString($totp->secret()));
        }
        $secret = Crypt::decryptString($request->session()->get('studio_mfa_pending_secret'));
        $issuer = 'Employee ID Studio';
        $label = $issuer.':'.$user->email;
        $uri = 'otpauth://totp/'.rawurlencode($label).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
        $qrSvg = (new Writer(new ImageRenderer(new RendererStyle(220, 0), new SvgImageBackEnd())))->writeString($uri);
        return response()->view('studio.auth.mfa-setup', compact('secret', 'qrSvg'))
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function confirm(Request $request, StudioTotp $totp, StudioAudit $audit)
    {
        $request->validate(['code' => ['required','digits:6']]);
        $user = $request->user();
        abort_unless($user && $user->canViewStudio() && $user->studio_company_id, 403);
        abort_if($user->studio_mfa_enabled_at || !$request->session()->has('studio_mfa_pending_secret'), 409);
        $secret = Crypt::decryptString($request->session()->get('studio_mfa_pending_secret'));
        $counter = $totp->match($secret, (string) $request->input('code'));
        if ($counter === null) {
            throw ValidationException::withMessages(['code' => 'Invalid authenticator code.']);
        }
        $codes = $totp->recoveryCodes();
        DB::transaction(function () use ($user, $secret, $counter, $codes, $audit): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->studio_mfa_enabled_at, 409);
            $locked->studio_mfa_secret = $secret;
            $locked->studio_mfa_recovery_hashes = array_map(fn (string $code) => hash('sha256', $code), $codes);
            $locked->studio_mfa_last_counter = $counter;
            $locked->studio_mfa_enabled_at = now();
            $locked->save();
            $audit->record($locked, 'auth.mfa_enrolled', $locked, ['studio_mfa_enabled_at']);
        });
        $request->session()->forget('studio_mfa_pending_secret');
        $request->session()->regenerate();
        $request->session()->put('studio_mfa_verified_user_id', $user->id);
        $request->session()->flash('studio_mfa_recovery_codes', $codes);
        return redirect()->route('studio.mfa.recovery');
    }

    public function challenge(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->canViewStudio() && $user->studio_company_id, 403);
        if (!$user->studio_mfa_enabled_at || !$user->studio_mfa_secret) {
            return redirect()->route('studio.mfa.setup');
        }
        if ((int) $request->session()->get('studio_mfa_verified_user_id', 0) === (int) $user->id) {
            return redirect()->route('studio.dashboard');
        }
        return response()->view('studio.auth.mfa-challenge')->header('Cache-Control','private, no-store');
    }

    public function verify(Request $request, StudioTotp $totp, StudioAudit $audit)
    {
        $data = $request->validate([
            'code' => ['required','string','max:40'],
        ]);
        $user = $request->user();
        abort_unless($user?->canViewStudio() && $user->studio_company_id, 403);
        abort_unless($user->studio_mfa_enabled_at && $user->studio_mfa_secret, 403);
        $code = strtoupper(trim((string) $data['code']));
        $passed = DB::transaction(function () use ($user, $code, $totp, $audit): bool {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $counter = $totp->match($locked->studio_mfa_secret, $code, $locked->studio_mfa_last_counter);
            if ($counter !== null) {
                $locked->studio_mfa_last_counter = $counter;
                $locked->save();
                return true;
            }
            $hash = hash('sha256', $code);
            $saved = $locked->studio_mfa_recovery_hashes ?? [];
            foreach ($saved as $index => $value) {
                if (hash_equals($value, $hash)) {
                    unset($saved[$index]);
                    $locked->studio_mfa_recovery_hashes = array_values($saved);
                    $locked->save();
                    $audit->record($locked, 'auth.mfa_recovery_used', $locked, ['studio_mfa_recovery_hashes']);
                    return true;
                }
            }
            return false;
        });
        if (!$passed) {
            throw ValidationException::withMessages(['code' => 'Invalid or already used verification code.']);
        }
        $request->session()->regenerate();
        $request->session()->put('studio_mfa_verified_user_id', $user->id);
        return redirect()->intended(route('studio.dashboard'));
    }

    public function recovery(Request $request)
    {
        abort_unless((int) $request->session()->get('studio_mfa_verified_user_id', 0) === (int) $request->user()->id, 403);
        $codes = $request->session()->get('studio_mfa_recovery_codes');
        abort_unless(is_array($codes) && count($codes) === 8, 404);
        return response()->view('studio.auth.mfa-recovery', ['codes' => $codes])
            ->header('Cache-Control','private, no-store')
            ->header('Referrer-Policy','no-referrer');
    }
}
