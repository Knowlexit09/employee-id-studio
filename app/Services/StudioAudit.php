<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\User;

final class StudioAudit
{
    public function record(User $actor, string $action, object $subject, array $fields = []): void
    {
        // Never write employee values / government numbers to audit rows.
        DB::table('studio_audits')->insert([
            'company_id' => $actor->studio_company_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->id,
            'field_names' => json_encode(array_values($fields), JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
