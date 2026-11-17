<?php

namespace Tests\Feature;

use App\Models\StudioCompany;
use App\Models\User;
use App\Services\StudioTotp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StudioMfaTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $company = StudioCompany::create(['uuid'=>(string)Str::uuid(),'name'=>'Secure Example']);
        $user=User::factory()->create();
        $user->studio_company_id=$company->id;
        $user->studio_role='hr';
        $user->studio_active=true;
        $user->save();
        return $user;
    }

    private function enroll(User $user): array
    {
        $this->actingAs($user)->get('/studio/mfa/setup')->assertOk()->assertSee('Set up two-factor');
        $secret = Crypt::decryptString(session('studio_mfa_pending_secret'));
        $code = app(StudioTotp::class)->code($secret, intdiv(now()->getTimestamp(),30));
        $this->actingAs($user)->post('/studio/mfa/setup',['code'=>$code])
            ->assertRedirect('/studio/mfa/recovery');
        $recovery = session('studio_mfa_recovery_codes');
        $this->assertCount(8,$recovery);
        return [$secret,$recovery];
    }

    public function test_authenticated_password_only_session_cannot_access_registry(): void
    {
        $user=$this->staff();
        $this->actingAs($user)->get('/studio')->assertRedirect('/studio/mfa/setup');
        $this->actingAs($user)->postJson('/studio/templates',[
            'name'=>'Unauthorized','width_mm'=>85.6,'height_mm'=>53.98,
        ])->assertForbidden();
        $this->assertDatabaseCount('studio_templates',0);
    }

    public function test_enrollment_secret_is_encrypted_and_unverified_requests_fail_closed(): void
    {
        $user=$this->staff();
        [$secret,$recovery]=$this->enroll($user);
        $this->assertNotSame($secret, \DB::table('users')->where('id',$user->id)->value('studio_mfa_secret'));
        $this->assertSame($secret,$user->fresh()->studio_mfa_secret);
        $this->assertNotContains($recovery[0],$user->fresh()->studio_mfa_recovery_hashes);
        $this->actingAs($user)->get('/studio')->assertOk();
        $this->post('/logout')->assertRedirect('/studio/login');
        $this->post('/login',['email'=>$user->email,'password'=>'password'])
            ->assertRedirect('/studio/mfa/challenge');
        $this->get('/studio')->assertRedirect('/studio/mfa/challenge');
        $this->postJson('/studio/templates',[
            'name'=>'Blocked','width_mm'=>85.6,'height_mm'=>53.98,
        ])->assertForbidden();
    }

    public function test_recovery_code_is_single_use_and_wrong_code_cannot_bypass_mfa(): void
    {
        $user=$this->staff();
        [$secret,$recovery]=$this->enroll($user);
        $this->post('/logout');
        $this->post('/login',['email'=>$user->email,'password'=>'password']);
        $this->post('/studio/mfa/challenge',['code'=>'000000'])
            ->assertSessionHasErrors('code');
        $this->get('/studio')->assertRedirect('/studio/mfa/challenge');
        $this->post('/studio/mfa/challenge',['code'=>$recovery[0]])
            ->assertRedirect('/studio');
        $this->get('/studio')->assertOk();

        $this->post('/logout');
        $this->post('/login',['email'=>$user->email,'password'=>'password']);
        $this->post('/studio/mfa/challenge',['code'=>$recovery[0]])
            ->assertSessionHasErrors('code');
        $this->get('/studio')->assertRedirect('/studio/mfa/challenge');
        $this->assertCount(7,$user->fresh()->studio_mfa_recovery_hashes);
    }
}
