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
                ...$data,'company_id'=>$company,'draft_layout'=>TemplateLayout::initial((float)$data['width_mm'], (float)$data['height_mm']),
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
            // Any edit invalidates an outstanding approval; approvals are bound to a revision.
            $record->approval_status='none';
            $record->approval_requested_revision=null;
            $record->approval_requested_by=null;
            $record->approval_requested_at=null;
            $record->approval_rejection_reason=null;
            $record->save();
            $audit->record($request->user(),'template.draft_saved',$record,['draft_layout','revision']);
            return $record->revision;
        });
        return response()->json(['revision'=>$saved,'saved'=>true]);
    }

    public function requestApproval(Request $request, int $template, StudioAudit $audit)
    {
        $company = $this->writeAccess($request);
        $data = $request->validate(['revision'=>['required','integer','min:1']]);
        DB::transaction(function () use ($request,$company,$template,$audit,$data): void {
            $record = StudioTemplate::where('company_id',$company)->whereNull('archived_at')->lockForUpdate()->findOrFail($template);
            if ($record->revision !== (int) $data['revision']) {
                abort(409,'Template changed; reload the draft before requesting approval.');
            }
            if ($record->approval_status === 'pending') {
                abort(409,'This revision is already awaiting review.');
            }
            $layout = TemplateLayout::normalize($record->draft_layout,(float)$record->width_mm,(float)$record->height_mm);
            $this->checkAssets($company,$record->id,$layout);
            $record->approval_status = 'pending';
            $record->approval_requested_revision = $record->revision;
            $record->approval_requested_by = $request->user()->id;
            $record->approval_requested_at = now();
            $record->approval_rejection_reason = null;
            $record->save();
            $audit->record($request->user(),'template.approval_requested',$record,['revision','approval_status']);
        });
        return response()->json(['requested'=>true,'revision'=>(int)$data['revision']]);
    }

    public function review(Request $request, int $template)
    {
        $company = $this->approvalAccess($request);
        $record = StudioTemplate::where('company_id',$company)->whereNull('archived_at')->findOrFail($template);
        $assets = StudioAsset::where('company_id',$company)->where('template_id',$record->id)->get()->keyBy('id');
        return view('studio.templates.review', ['template'=>$record,'assets'=>$assets]);
    }

    public function publish(Request $request, int $template, StudioAudit $audit)
    {
        $company = $this->approvalAccess($request);
        $data = $request->validate([
            'revision'=>['required','integer','min:1'],
            'review_confirmed'=>['required','accepted'],
        ]);
        $version = DB::transaction(function () use ($request,$company,$template,$audit,$data) {
            $record = StudioTemplate::where('company_id',$company)->whereNull('archived_at')->lockForUpdate()->findOrFail($template);
            if ($record->approval_status !== 'pending' ||
                $record->approval_requested_revision !== (int)$data['revision'] ||
                $record->revision !== (int)$data['revision']) {
                abort(409,'Approval must target the current requested revision.');
            }
            if ((int)$record->approval_requested_by === (int)$request->user()->id) {
                abort(403,'The designer cannot approve their own draft.');
            }
            $layout = TemplateLayout::normalize($record->draft_layout,(float)$record->width_mm,(float)$record->height_mm);
            $this->checkAssets($company,$record->id,$layout);
            $number = (int)$record->versions()->max('version') + 1;
            $version = StudioTemplateVersion::create([
                'company_id'=>$company,'template_id'=>$record->id,'version'=>$number,
                'layout'=>$layout,'width_mm'=>$record->width_mm,'height_mm'=>$record->height_mm,
                'published_at'=>now(),'published_by'=>$request->user()->id,
            ]);
            $record->published_version_id = $version->id;
            $record->approval_status = 'none';
            $record->approval_requested_revision = null;
            $record->approval_requested_by = null;
            $record->approval_requested_at = null;
            $record->approval_rejection_reason = null;
            $record->save();
            $audit->record($request->user(),'template.approved_published',$record,['published_version_id','approval_status']);
            return $version;
        });
        if ($request->expectsJson()) {
            return response()->json(['published_version'=>$version->version]);
        }
        return redirect()->route('studio.templates.history',$template)->with('status','Template approved and published.');
    }

    public function reject(Request $request, int $template, StudioAudit $audit)
    {
        $company = $this->approvalAccess($request);
        $data = $request->validate([
            'revision'=>['required','integer','min:1'],
            'reason'=>['required','string','min:5','max:240'],
        ]);
        DB::transaction(function () use ($request,$company,$template,$audit,$data): void {
            $record = StudioTemplate::where('company_id',$company)->whereNull('archived_at')->lockForUpdate()->findOrFail($template);
            if ($record->approval_status !== 'pending' ||
                $record->approval_requested_revision !== (int)$data['revision'] ||
                $record->revision !== (int)$data['revision']) {
                abort(409,'Review request is no longer current.');
            }
            if ((int)$record->approval_requested_by === (int)$request->user()->id) {
                abort(403,'The designer cannot review their own draft.');
            }
            $record->approval_status = 'rejected';
            $record->approval_rejection_reason = $data['reason'];
            $record->save();
            $audit->record($request->user(),'template.rejected',$record,['approval_status','reason_supplied']);
        });
        return redirect()->route('studio.templates.review',$template)->with('status','Revision rejected. The designer can revise and resubmit.');
    }

    public function history(Request $request, int $template)
    {
        $company = $this->viewAccess($request);
        $record = StudioTemplate::where('company_id',$company)->findOrFail($template);
        $versions = StudioTemplateVersion::where('company_id',$company)->where('template_id',$record->id)
            ->orderByDesc('version')->paginate(20);
        return view('studio.templates.history', ['template'=>$record,'versions'=>$versions]);
    }

    public function historyVersion(Request $request, int $template, int $version)
    {
        $company = $this->viewAccess($request);
        $record = StudioTemplate::where('company_id',$company)->findOrFail($template);
        $published = StudioTemplateVersion::where('company_id',$company)->where('template_id',$record->id)
            ->where('version',$version)->firstOrFail();
        return view('studio.templates.history-version',['template'=>$record,'version'=>$published]);
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
