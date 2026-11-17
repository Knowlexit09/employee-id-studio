<?php

namespace Tests\Feature;

use App\Models\StudioCardIssue;
use App\Models\StudioCompany;
use App\Models\StudioEmployee;
use App\Models\StudioTemplate;
use App\Models\User;
use App\Services\TemplateLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StudioBulkPrintTest extends TestCase
{
    use RefreshDatabase;

    private function createIssuedCard(): array
    {
        $company = StudioCompany::create(['uuid'=>(string) Str::uuid(),'name'=>'Example Studio']);
        $hr = User::factory()->create();
        $hr->studio_company_id = $company->id;
        $hr->studio_role = 'hr';
        $hr->studio_active = true;
        $hr->save();

        $employee = new StudioEmployee([
            'employee_code'=>'EMP-100','first_name'=>'Mia','last_name'=>'Example','status'=>'active',
        ]);
        $employee->company_id = $company->id;
        $employee->uuid = (string) Str::uuid();
        $employee->save();

        $template = StudioTemplate::create([
            'company_id'=>$company->id,'created_by'=>$hr->id,
            'name'=>'Company ID','width_mm'=>85.6,'height_mm'=>53.98,
            'revision'=>1,'draft_layout'=>TemplateLayout::initial(),
        ]);
        $this->actingAs($hr)->postJson("/studio/templates/{$template->id}/publish", [
            'revision'=>1,
        ])->assertOk();
        $this->actingAs($hr)->post("/studio/employees/{$employee->id}/cards", [
            'template_id'=>$template->id,
        ])->assertRedirect();

        return [$company, $hr, $employee, StudioCardIssue::firstOrFail()];
    }

    public function test_bulk_print_reuses_existing_issue_and_published_layout(): void
    {
        [$company, $hr, $employee, $issue] = $this->createIssuedCard();
        $count = StudioCardIssue::count();

        $this->actingAs($hr)->get('/studio/cards/bulk')
            ->assertOk()->assertSee('EMP-100')->assertSee('Bulk ID printing');

        $this->actingAs($hr)->post('/studio/cards/bulk/print', [
            'ids'=>[$issue->id],
        ])->assertOk()
          ->assertSee('Front / Back')
          ->assertSee('EMP-100')
          ->assertHeader('Cache-Control','no-store, private');
        $this->assertSame($count, StudioCardIssue::count(), 'Bulk printing must not issue new QR tokens');
    }

    public function test_bulk_print_cannot_access_other_companies_cards(): void
    {
        [$company, $hr, $employee, $issue] = $this->createIssuedCard();
        $other = StudioCompany::create(['uuid'=>(string)Str::uuid(),'name'=>'Other Company']);
        $otherHr = User::factory()->create();
        $otherHr->studio_company_id = $other->id;
        $otherHr->studio_role = 'hr';
        $otherHr->studio_active = true;
        $otherHr->save();
        $this->actingAs($otherHr)->postJson('/studio/cards/bulk/print',[
            'ids'=>[$issue->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('ids');
    }

    public function test_bulk_print_rejects_revoked_and_expired_cards(): void
    {
        [$company, $hr, $employee, $issue] = $this->createIssuedCard();
        $issue->status = 'revoked';
        $issue->save();
        $this->actingAs($hr)->postJson('/studio/cards/bulk/print', [
            'ids'=>[$issue->id],
        ])->assertUnprocessable();

        $issue->status = 'active';
        $issue->expires_at = now()->subDay()->toDateString();
        $issue->save();
        $this->actingAs($hr)->postJson('/studio/cards/bulk/print', [
            'ids'=>[$issue->id],
        ])->assertUnprocessable();
    }

    public function test_bulk_print_rejects_duplicate_and_oversized_selection(): void
    {
        [$company, $hr, $employee, $issue] = $this->createIssuedCard();
        $this->actingAs($hr)->postJson('/studio/cards/bulk/print', [
            'ids'=>[$issue->id, $issue->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('ids.1');

        $this->actingAs($hr)->postJson('/studio/cards/bulk/print', [
            'ids'=>range(1,25),
        ])->assertUnprocessable()->assertJsonValidationErrors('ids');
    }
}
