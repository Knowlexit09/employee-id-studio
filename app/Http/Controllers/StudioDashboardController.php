<?php
namespace App\Http\Controllers;

use App\Models\StudioEmployee;
use App\Models\StudioTemplate;
use App\Models\StudioCardIssue;
use Illuminate\Http\Request;

final class StudioDashboardController extends StudioController
{
    public function __invoke(Request $request)
    {
        $company = $this->viewAccess($request);
        return view('studio.dashboard', [
            'employees'=>StudioEmployee::where('company_id',$company)->count(),
            'templates'=>StudioTemplate::where('company_id',$company)->whereNull('archived_at')->count(),
            'issued'=>StudioCardIssue::where('company_id',$company)->where('status','active')->count(),
        ]);
    }
}
