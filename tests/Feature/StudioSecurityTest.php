<?php
namespace Tests\Feature;

use App\Models\StudioCompany;
use App\Models\StudioEmployee;
use App\Models\StudioTemplate;
use App\Models\User;
use App\Services\TemplateLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_calibration_sheet_requires_authentication_and_contains_exact_measurements(): void
    {
        $this->get('/studio/print-calibration')->assertRedirect('/login');
        $company = $this->company('Calibration Studio');
        $viewer = $this->staff($company, 'manager');
        $this->actingAs($viewer)->get('/studio/print-calibration')
            ->assertOk()
            ->assertSee('50 mm × 50 mm')
            ->assertSee('100 mm')
            ->assertSee('100% / Actual size');
    }

    public function test_template_editor_renders_precision_controls_for_hr(): void
    {
        $company = $this->company('Precision Company');
        $hr = $this->staff($company);
        $template = $this->template($company, $hr);
        $this->actingAs($hr)->get("/studio/templates/{$template->id}/edit")
            ->assertOk()
            ->assertSee('id="id-canvas"', false)
            ->assertSee('id="smart-guides"', false)
            ->assertSee('id="toggle-preview"', false)
            ->assertSee('id="layer-front"', false)
            ->assertSee('data-align="hcenter"', false);
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

    public function test_employee_template_creation_scales_custom_card_layout(): void
    {
        $company = $this->company('Co A');
        $hr = $this->staff($company);

        $response = $this->actingAs($hr)->post('/studio/templates', [
            'name' => 'Custom portrait',
            'width_mm' => 54.0,
            'height_mm' => 85.6,
        ]);
        $template = StudioTemplate::where('company_id', $company->id)->firstOrFail();
        $response->assertRedirect("/studio/templates/{$template->id}/edit");

        $layout = $template->draft_layout;
        $normalized = TemplateLayout::normalize($layout, 54.0, 85.6);
        $this->assertEquals($layout, $normalized);
        $this->assertLessThanOrEqual(54.01, $layout['front']['elements'][0]['x'] + $layout['front']['elements'][0]['w']);
        $this->actingAs($hr)->postJson("/studio/templates/{$template->id}/publish", [
            'revision' => 1,
        ])->assertOk()->assertJsonPath('published_version', 1);
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
        $this->actingAs($hr)->get("/studio/cards/{$issue->id}/print")
            ->assertOk()->assertSee('card-invalid-overlay');
    }
    public function test_statutory_numbers_are_masked_and_not_cleared_by_blank_update(): void
    {
        $company = $this->company('Private Employer');
        $hr = $this->staff($company);
        $employee = $this->employee($company);
        $employee->sss_number = '12-3456789-0';
        $employee->tin = '123-456-789';
        $employee->save();

        $this->actingAs($hr)->get("/studio/employees/{$employee->id}/edit")
            ->assertOk()->assertDontSee('12-3456789-0')->assertDontSee('123-456-789')
            ->assertSee('Saved securely');

        $form = [
            'employee_code' => 'EMP-1', 'first_name' => 'Jane',
            'last_name' => 'Example', 'status' => 'active', 'job_title' => 'Accountant',
            'sss_number' => '', 'tin' => '',
        ];
        $this->actingAs($hr)->put("/studio/employees/{$employee->id}", $form)->assertRedirect();
        $employee->refresh();
        $this->assertSame('12-3456789-0', $employee->sss_number);
        $this->assertSame('123-456-789', $employee->tin);
        $this->assertSame('Accountant', $employee->job_title);

        $this->actingAs($hr)->put("/studio/employees/{$employee->id}", [
            ...$form, 'clear_sss_number' => '1',
        ])->assertRedirect();
        $employee->refresh();
        $this->assertNull($employee->sss_number);
        $this->assertSame('123-456-789', $employee->tin);
    }

    public function test_employee_photo_is_converted_to_private_jpeg(): void
    {
        Storage::fake('local');
        $company = $this->company('Photo Company');
        $hr = $this->staff($company);
        $this->actingAs($hr)->post('/studio/employees', [
            'employee_code'=>'EMP-2', 'first_name'=>'Mia',
            'last_name'=>'Example', 'status'=>'active',
            'photo'=>UploadedFile::fake()->image('source.png', 1600, 800),
        ])->assertRedirect();

        $employee = StudioEmployee::where('employee_code', 'EMP-2')->firstOrFail();
        $this->assertStringEndsWith('.jpg', $employee->photo_path);
        Storage::disk('local')->assertExists($employee->photo_path);
        $bytes = Storage::disk('local')->get($employee->photo_path);
        $info = getimagesizefromstring($bytes);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
        $this->assertLessThanOrEqual(1200, $info[0]);
        $this->actingAs($hr)->get("/studio/employees/{$employee->id}/photo")
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_cross_company_photo_update_is_denied_without_writing_file(): void
    {
        Storage::fake('local');
        $companyA = $this->company('Owner');
        $companyB = $this->company('Other');
        $employee = $this->employee($companyA);
        $otherHr = $this->staff($companyB);
        $this->actingAs($otherHr)->put("/studio/employees/{$employee->id}", [
            'employee_code'=>'EMP-1', 'first_name'=>'Jane',
            'last_name'=>'Example', 'status'=>'active',
            'photo'=>UploadedFile::fake()->image('new.png', 200, 200),
        ])->assertNotFound();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

}
