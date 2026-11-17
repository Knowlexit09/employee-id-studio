<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireStudioMfa;
use App\Models\StudioCompany;
use App\Models\StudioTemplate;
use App\Models\StudioTemplateVersion;
use App\Models\User;
use App\Services\TemplateLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StudioApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(RequireStudioMfa::class);
    }

    private function company(string $name): StudioCompany
    {
        return StudioCompany::create(['uuid'=>(string)Str::uuid(),'name'=>$name]);
    }

    private function staff(StudioCompany $company,string $role): User
    {
        $user = User::factory()->create();
        $user->studio_company_id=$company->id;
        $user->studio_role=$role;
        $user->studio_active=true;
        $user->save();
        return $user;
    }

    private function template(StudioCompany $company, User $designer): StudioTemplate
    {
        return StudioTemplate::create([
            'company_id'=>$company->id,'name'=>'Standard','created_by'=>$designer->id,
            'draft_layout'=>TemplateLayout::initial(),'width_mm'=>85.6,
            'height_mm'=>53.98,'revision'=>1,
        ]);
    }

    public function test_hr_requests_and_manager_approves_exact_revision(): void
    {
        $company=$this->company('Employer');
        $hr=$this->staff($company,'hr');
        $manager=$this->staff($company,'manager');
        $template=$this->template($company,$hr);

        $this->actingAs($hr)->postJson("/studio/templates/{$template->id}/publish",['revision'=>1])
            ->assertForbidden();
        $this->actingAs($manager)->postJson("/studio/templates/{$template->id}/publish",['revision'=>1])
            ->assertStatus(409);

        $this->actingAs($hr)->postJson("/studio/templates/{$template->id}/approval-request",['revision'=>1])
            ->assertOk()->assertJsonPath('requested',true);
        $this->actingAs($manager)->get("/studio/templates/{$template->id}/review")
            ->assertOk()->assertSee('Independent template approval');
        $this->actingAs($manager)->postJson("/studio/templates/{$template->id}/publish",['revision'=>1])
            ->assertOk()->assertJsonPath('published_version',1);
        $this->assertSame('none',$template->fresh()->approval_status);
        $this->assertSame(1,StudioTemplateVersion::where('template_id',$template->id)->count());
        $this->actingAs($hr)->get("/studio/templates/{$template->id}/history")
            ->assertOk()->assertSee('Version');
        $this->actingAs($hr)->get("/studio/templates/{$template->id}/history/1")
            ->assertOk()->assertSee('Physical size');
        $this->actingAs($manager)->postJson("/studio/templates/{$template->id}/publish",['revision'=>1])
            ->assertStatus(409);
    }

    public function test_self_approval_is_forbidden_even_for_super_admin(): void
    {
        $company=$this->company('Employer');
        $admin=$this->staff($company,'super_admin');
        $template=$this->template($company,$admin);
        $this->actingAs($admin)->postJson("/studio/templates/{$template->id}/approval-request",['revision'=>1])->assertOk();
        $this->actingAs($admin)->postJson("/studio/templates/{$template->id}/publish",['revision'=>1])->assertForbidden();
        $this->assertDatabaseCount('studio_template_versions',0);
    }

    public function test_edit_after_request_invalidates_pending_approval(): void
    {
        $company=$this->company('Employer');
        $hr=$this->staff($company,'hr');
        $manager=$this->staff($company,'manager');
        $template=$this->template($company,$hr);
        $this->actingAs($hr)->postJson("/studio/templates/{$template->id}/approval-request",['revision'=>1])->assertOk();
        $this->actingAs($hr)->putJson("/studio/templates/{$template->id}/draft",[
            'revision'=>1,'layout'=>TemplateLayout::initial(),
        ])->assertOk()->assertJsonPath('revision',2);
        $this->assertSame('none',$template->fresh()->approval_status);
        $this->actingAs($manager)->postJson("/studio/templates/{$template->id}/publish",['revision'=>1])
            ->assertStatus(409);
    }

    public function test_reviewer_rejection_and_tenant_isolation(): void
    {
        $a=$this->company('A');$b=$this->company('B');
        $hr=$this->staff($a,'hr');
        $reviewer=$this->staff($a,'manager');
        $foreign=$this->staff($b,'manager');
        $template=$this->template($a,$hr);
        $this->actingAs($hr)->postJson("/studio/templates/{$template->id}/approval-request",['revision'=>1])->assertOk();
        $this->actingAs($foreign)->postJson("/studio/templates/{$template->id}/publish",['revision'=>1])->assertNotFound();
        $this->actingAs($foreign)->get("/studio/templates/{$template->id}/history")->assertNotFound();
        $this->actingAs($reviewer)->post("/studio/templates/{$template->id}/reject",[
            'revision'=>1,'reason'=>'Wrong logo colors',
        ])->assertRedirect();
        $this->assertSame('rejected',$template->fresh()->approval_status);
        $this->actingAs($reviewer)->postJson("/studio/templates/{$template->id}/publish",['revision'=>1])->assertStatus(409);
    }
}
