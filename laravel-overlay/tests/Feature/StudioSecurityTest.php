<?php
namespace Tests\Feature;

use App\Models\StudioCompany;
use App\Models\StudioEmployee;
use App\Models\StudioTemplate;
use App\Models\User;
use App\Services\TemplateLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StudioSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): StudioCompany
    {
        return StudioCompany::create(['uuid'=>(string)Str::uuid(),'name'=>$name]);
    }

    private function staff(StudioCompany $company, string $role='hr'): User
    {
        $user=User::factory()->create();
        $user->studio_company_id=$company->id;
        $user->studio_role=$role;
        $user->studio_active=true;
        $user->save();
        return $user;
    }

    private function employee(StudioCompany $company): StudioEmployee
    {
        $e=new StudioEmployee(['employee_code'=>'EMP-1','first_name'=>'Jane','last_name'=>'Example','status'=>'active']);
        $e->company_id=$company->id;$e->uuid=(string)Str::uuid();$e->save();return $e;
    }

    private function template(StudioCompany $company, User $user): StudioTemplate
    {
        return StudioTemplate::create([
            'company_id'=>$company->id,'name'=>'Corporate ID','draft_layout'=>TemplateLayout::initial(),
            'width_mm'=>85.6,'height_mm'=>53.98,'revision'=>1,'created_by'=>$user->id,
        ]);
    }

    public function test_guest_cannot_access_employee_registry(): void
    {
        $this->get('/studio/employees')->assertRedirect('/login');
    }

    public function test_manager_cannot_edit_employee_or_template(): void
    {
        $c=$this->company('Co A');$manager=$this->staff($c,'manager');
        $this->actingAs($manager)->get('/studio/employees/create')->assertForbidden();
        $this->actingAs($manager)->post('/studio/templates',['name'=>'Bad','width_mm'=>85.6,'height_mm'=>53.98])->assertForbidden();
    }

    public function test_companies_cannot_view_each_others_employee(): void
    {
        $a=$this->company('Co A');$b=$this->company('Co B');
        $employee=$this->employee($a);$hr=$this->staff($b);
        $this->actingAs($hr)->get("/studio/employees/{$employee->id}/edit")->assertNotFound();
    }

    public function test_government_numbers_are_encrypted_in_database(): void
    {
        $company=$this->company('Co A');$e=$this->employee($company);
        $e->sss_number='12-3456789-0';$e->save();
        $raw=\DB::table('studio_employees')->where('id',$e->id)->value('sss_number');
        $this->assertNotSame('12-3456789-0',$raw);
        $this->assertSame('12-3456789-0',$e->fresh()->sss_number);
    }

    public function test_saving_a_template_uses_optimistic_concurrency(): void
    {
        $company=$this->company('Co A');$hr=$this->staff($company);
        $template=$this->template($company,$hr);
        $this->actingAs($hr)->putJson("/studio/templates/{$template->id}/draft",[
            'revision'=>1,'layout'=>TemplateLayout::initial(),
        ])->assertOk()->assertJsonPath('revision',2);
        $this->actingAs($hr)->putJson("/studio/templates/{$template->id}/draft",[
            'revision'=>1,'layout'=>TemplateLayout::initial(),
        ])->assertStatus(409);
    }

    public function test_government_number_is_not_valid_id_template_field(): void
    {
        $company=$this->company('Co A');$hr=$this->staff($company);
        $template=$this->template($company,$hr);
        $layout=TemplateLayout::initial();$layout['front']['elements'][0]['binding']='sss_number';
        $this->actingAs($hr)->putJson("/studio/templates/{$template->id}/draft",[
            'revision'=>1,'layout'=>$layout,
        ])->assertUnprocessable();
    }

    public function test_revoked_qr_cannot_be_verified(): void
    {
        $company=$this->company('Co A');$hr=$this->staff($company);
        $employee=$this->employee($company);$template=$this->template($company,$hr);
        $this->actingAs($hr)->postJson("/studio/templates/{$template->id}/publish",['revision'=>1])->assertOk();
        $this->actingAs($hr)->post("/studio/employees/{$employee->id}/cards",['template_id'=>$template->id])->assertRedirect();
        $issue=\App\Models\StudioCardIssue::firstOrFail();
        $token=$issue->token_encrypted;
        $this->get("/verify-id/{$token}")->assertOk()->assertSee('VALID ID');
        $this->actingAs($hr)->post("/studio/cards/{$issue->id}/revoke",['reason'=>'Lost card'])->assertRedirect();
        $this->get("/verify-id/{$token}")->assertOk()->assertSee('INVALID / REVOKED ID');
    }
}
