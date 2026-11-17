<?php
namespace App\Http\Controllers;

use App\Models\StudioAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class StudioAssetController extends StudioController
{
    public function show(Request $request, int $asset)
    {
        $company=$this->viewAccess($request);
        $record=StudioAsset::where('company_id',$company)->findOrFail($asset);
        abort_unless(Storage::disk('local')->exists($record->path),404);
        return Storage::disk('local')->response($record->path, null, [
            'Content-Type'=>$record->mime,'X-Content-Type-Options'=>'nosniff',
            'Cache-Control'=>'private,max-age=300','Content-Security-Policy'=>"default-src 'none'; sandbox",
        ]);
    }
}
