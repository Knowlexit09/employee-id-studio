<?php
namespace App\Services;

use App\Models\StudioEmployee;
use App\Models\StudioCompany;
use App\Models\StudioCardIssue;

final class CardFieldResolver
{
    public function snapshot(StudioEmployee $employee, StudioCompany $company): array
    {
        return [
            'first_name'=>$employee->first_name,
            'last_name'=>$employee->last_name,
            'full_name'=>$employee->full_name,
            'employee_code'=>$employee->employee_code,
            'job_title'=>$employee->job_title ?? '',
            'department'=>$employee->department ?? '',
            'branch'=>$employee->branch ?? '',
            'email'=>$employee->email ?? '',
            'company_name'=>$company->name,
        ];
    }

    public function forIssue(StudioCardIssue $issue): array
    {
        return array_merge($issue->display_snapshot, [
            'issue_date'=>$issue->issued_at->format('Y-m-d'),
            'expiry_date'=>$issue->expires_at?->format('Y-m-d') ?? '',
            'verification_url'=>route('studio.verify',['token'=>$issue->token_encrypted]),
        ]);
    }
}
