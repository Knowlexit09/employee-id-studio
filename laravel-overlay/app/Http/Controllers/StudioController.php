<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class StudioController extends Controller
{
    protected function viewAccess(Request $request): int
    {
        abort_unless($request->user()?->canViewStudio() === true && $request->user()->studio_company_id, 403);
        return (int) $request->user()->studio_company_id;
    }

    protected function writeAccess(Request $request): int
    {
        abort_unless($request->user()?->canManageStudio() === true && $request->user()->studio_company_id, 403);
        return (int) $request->user()->studio_company_id;
    }
}
