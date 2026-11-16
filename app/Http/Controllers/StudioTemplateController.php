<?php
namespace App\Http\Controllers;

use App\Models\StudioTemplate;
use App\Models\StudioTemplateVersion;
use App\Models\StudioAsset;
use App\Services\StudioAudit;
use App\Services\TemplateLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class StudioTemplateController extends StudioController
{
    public function index(Request $request)
    {
        $company=$this->viewAccess($request);
        return view('studio.templates.index',[
            'templates'=>StudioTemplate::where('company_id',$company)->whereNull('archived_at')->latest()->paginate(15),
        ]);
    }

    public function create(Request $request, StudioAudit $audit)
    {
        $company=$this->writeAccess($request);
        $data=$request->validate([
            'name'=>['required','string','max:120'],
            'width_mm'=>['required','numeric','between:40,220'],
            'height_mm'=>['required','numeric','between:30,310'],
        ]);
        $template=DB::transaction(function () use ($request,$company,$data,$audit) {
            $template=StudioTemplate::create([
                ...$data,'company_id'=>$company,'draft_layout'=>TemplateLayout::initial(),
                'created_by'=>$request->user()->id,'revision'=>1,
            ]);
            $audit->record($request->user(),'template.created',$template,['name','width_mm','height_mm']);
            return $template;
        });
        return redirect()->route('studio.templates.edit',$template->id);
    }

    public function edit(Request $request, int $template)
    {
        $company=$this->writeAccess($request);
        $record=StudioTemplate::where('company_id',$company)->whereNull('archived_at')->findOrFail($template);
        $assets=StudioAsset::where('company_id',$company)->where('template_id',$record->id)->get();
        return view('studio.templates.editor',['template'=>$record,'assets'=>$assets]);
    }

    public function save(Request $request, int $template, StudioAudit $audit)
    {
        $company=$this->writeAccess($request);
        $request->validate(['revision'=>['required','integer','min:1'],'layout'=>['required','array']]);
        $saved=DB::transaction(function () use ($request,$company,$template,$audit) {
            $record=StudioTemplate::where('company_id',$company)->whereNull('archived_at')->lockForUpdate()->findOrFail($template);
            if ($record->revision !== (int)$request->input('revision')) { abort(409,'Template was changed elsewhere. Reload before editing.'); }
            $layout=TemplateLayout::normalize($request->input('layout'),(float)$record->width_mm,(float)$record->height_mm);
            $this->checkAssets($company,$record->id,$layout);
            $record->draft_layout=$layout;
            $record->revision++;
            $record->save();
            $audit->record($request->user(),'template.draft_saved',$record,['draft_layout','revision']);
            return $record->revision;
        });
        return response()->json(['revision'=>$saved,'saved'=>true]);
    }

    public function publish(Request $request, int $template, StudioAudit $audit)
    {
        $company=$this->writeAccess($request);
        $request->validate(['revision'=>['required','integer','min:1']]);
        $version=DB::transaction(function () use ($request,$company,$template,$audit) {
            $record=StudioTemplate::where('company_id',$company)->whereNull('archived_at')->lockForUpdate()->findOrFail($template);
            if ($record->revision !== (int)$request->input('revision')) { abort(409,'Save the current draft first.'); }
            $layout=TemplateLayout::normalize($record->draft_layout,(float)$record->width_mm,(float)$record->height_mm);
            $this->checkAssets($company,$record->id,$layout);
            $number=(int)$record->versions()->max('version')+1;
            $version=StudioTemplateVersion::create([
                'company_id'=>$company,'template_id'=>$record->id,'version'=>$number,
                'layout'=>$layout,'width_mm'=>$record->width_mm,'height_mm'=>$record->height_mm,
                'published_at'=>now(),'published_by'=>$request->user()->id,
            ]);
            $record->published_version_id=$version->id;
            $record->save();
            $audit->record($request->user(),'template.published',$record,['published_version_id']);
            return $version;
        });
        return response()->json(['published_version'=>$version->version]);
    }

    public function archive(Request $request,int $template,StudioAudit $audit)
    {
        $company=$this->writeAccess($request);
        DB::transaction(function () use ($request,$company,$template,$audit) {
            $record=StudioTemplate::where('company_id',$company)->whereNull('archived_at')->lockForUpdate()->findOrFail($template);
            $record->archived_at=now();$record->save();
            $audit->record($request->user(),'template.archived',$record,['archived_at']);
        });
        return redirect()->route('studio.templates.index')->with('status','Template archived. Existing issued cards retained.');
    }

    public function uploadAsset(Request $request, int $template, StudioAudit $audit)
    {
        $company=$this->writeAccess($request);
        $record=StudioTemplate::where('company_id',$company)->whereNull('archived_at')->findOrFail($template);
        $data=$request->validate([
            'kind'=>['required','in:background,logo'],
            'file'=>['required','image','mimes:jpeg,png,webp','max:3072','dimensions:max_width=4096,max_height=4096'],
        ]);
        $asset=DB::transaction(function () use ($company,$request,$record,$data,$audit) {
            $path=$request->file('file')->store("studio/{$company}/template-assets",'local');
            $asset=StudioAsset::create([
                'company_id'=>$company,'template_id'=>$record->id,
                'kind'=>$data['kind'],'path'=>$path,'mime'=>$request->file('file')->getMimeType(),
            ]);
            $audit->record($request->user(),'asset.uploaded',$asset,['kind']);
            return $asset;
        });
        return response()->json(['id'=>$asset->id,'kind'=>$asset->kind,'url'=>route('studio.assets.show',$asset->id)],201);
    }

    private function checkAssets(int $company, int $template, array $layout): void
    {
        foreach (['background_asset_id'=>'background', 'logo_asset_id'=>'logo'] as $key => $kind) {
            $ids = array_values(array_unique(array_filter([$layout['front'][$key], $layout['back'][$key]])));
            if (!$ids) { continue; }
            $valid=StudioAsset::where('company_id',$company)->where('template_id',$template)
                ->where('kind',$kind)->whereIn('id',$ids)->count();
            if ($valid!==count($ids)) { throw ValidationException::withMessages(['layout'=>"Invalid {$kind} asset reference."]); }
        }
    }
}
