<?php

namespace Tests\Unit;

use App\Services\StudioTotp;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class StudioTotpTest extends TestCase
{
    public function test_matches_rfc6238_sha1_six_digit_sample(): void
    {
        $totp = new StudioTotp();
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', $totp->code($secret, 1));
        Carbon::setTestNow(Carbon::createFromTimestampUTC(59));
        try {
            $this->assertSame(1, $totp->match($secret,'287082'));
            $this->assertNull($totp->match($secret,'287082',1));
            $this->assertNull($totp->match($secret,'123'));
            $this->assertNull($totp->match($secret,'AAB123'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_generated_secret_and_recovery_codes_are_high_entropy_and_unique(): void
    {
        $totp = new StudioTotp();
        $secret = $totp->secret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/D',$secret);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/D',$totp->code($secret,intdiv(now()->timestamp,30)));
        $codes = $totp->recoveryCodes();
        $this->assertCount(8,$codes);
        $this->assertCount(8,array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-F0-9]{6}(?:-[A-F0-9]{6}){3}$/D',$code);
        }
    }
}
