<?php
namespace App\Http\Controllers;

use App\Models\StudioAsset;
use App\Models\StudioCardIssue;
use App\Models\StudioCompany;
use App\Models\StudioEmployee;
use App\Models\StudioTemplate;
use App\Models\StudioTemplateVersion;
use App\Services\CardFieldResolver;
use App\Services\StudioAudit;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StudioCardController extends StudioController
{
    public function form(Request $request, int $employee)
    {
        $company=$this->writeAccess($request);
        $record=StudioEmployee::where('company_id',$company)->findOrFail($employee);
        $templates=StudioTemplate::where('company_id',$company)->whereNull('archived_at')
            ->whereNotNull('published_version_id')->orderBy('name')->get();
        $issues=StudioCardIssue::where('company_id',$company)->where('employee_id',$record->id)
            ->latest()->limit(15)->get();
        return view('studio.cards.form',compact('record','templates','issues'));
    }

    public function issue(Request $request, int $employee, StudioAudit $audit, CardFieldResolver $resolver)
    {
        $company=$this->writeAccess($request);
        $data=$request->validate([
            'template_id'=>['required','integer'],
            'expires_at'=>['nullable','date','after:today'],
        ]);
        $issue=DB::transaction(function () use ($request,$company,$employee,$data,$audit,$resolver) {
            $record=StudioEmployee::where('company_id',$company)->lockForUpdate()->findOrFail($employee);
            if ($record->status!=='active') { throw ValidationException::withMessages(['employee'=>'Inactive employee cannot receive an ID.']); }
            if (StudioCardIssue::where('company_id',$company)->where('employee_id',$employee)->where('status','active')->exists()) {
                throw ValidationException::withMessages(['employee'=>'Revoke the previous active ID first.']);
            }
            $template=StudioTemplate::where('company_id',$company)->whereNull('archived_at')->findOrFail($data['template_id']);
            $version=StudioTemplateVersion::where('company_id',$company)->where('template_id',$template->id)
                ->findOrFail($template->published_version_id);
            $token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
            $companyRecord=StudioCompany::findOrFail($company);
            $issue=StudioCardIssue::create([
                'uuid'=>(string)Str::uuid(),'company_id'=>$company,'employee_id'=>$record->id,
                'version_id'=>$version->id,'token_hash'=>hash('sha256',$token),'token_encrypted'=>$token,
                'display_snapshot'=>$resolver->snapshot($record,$companyRecord),
                'photo_path_snapshot'=>$record->photo_path,'status'=>'active','issued_by'=>$request->user()->id,
                'issued_at'=>now(),'expires_at'=>$data['expires_at'] ?? null,
            ]);
            $audit->record($request->user(),'card.issued',$issue,['employee_id','version_id','expires_at']);
            return $issue;
        });
        return redirect()->route('studio.cards.print',$issue->id)->with('status','ID issued. Print at actual size.');
    }

    /**
     * PII-free A4 calibration gauge for estimating actual printed dimensions.
     * Require studio membership even though there is no employee data on the sheet.
     */
    public function calibration(Request $request)
    {
        $this->viewAccess($request);
        return response()->view('studio.cards.calibration')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * A single company can select at most 24 existing valid cards for printing.
     */
    public function bulkIndex(Request $request)
    {
        $company = $this->viewAccess($request);
        $issues = StudioCardIssue::query()
            ->where('company_id', $company)
            ->where('status', 'active')
            ->whereHas('employee', fn ($q) => $q->where('status', 'active'))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', today()->toDateString()))
            ->with(['employee:id,employee_code,first_name,middle_name,last_name,suffix,status',
                    'version:id,template_id,version'])
            ->orderByDesc('issued_at')->orderByDesc('id')
            ->paginate(24);
        return view('studio.cards.bulk-index', compact('issues'));
    }

    public function bulkPrint(Request $request, CardFieldResolver $resolver)
    {
        $company = $this->viewAccess($request);
        $validated = $request->validate([
            'ids' => ['required','array','min:1','max:24'],
            'ids.*' => ['required','integer','distinct','min:1'],
        ]);
        $ids = array_map('intval', $validated['ids']);
        $records = StudioCardIssue::query()
            ->where('company_id', $company)
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->whereHas('employee', fn ($q) => $q->where('status', 'active'))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', today()->toDateString()))
            ->with(['employee', 'version'])
            ->get()->keyBy('id');
        if ($records->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids'=>'Selection includes an unavailable, expired or revoked card.']);
        }

        $templateIds = $records->pluck('version.template_id')->unique()->values()->all();
        $templates = StudioTemplate::where('company_id', $company)
            ->whereIn('id', $templateIds)->get()->keyBy('id');
        $assetsByTemplate = StudioAsset::where('company_id', $company)
            ->whereIn('template_id', $templateIds)->get()->groupBy('template_id');
        $writer = new Writer(new ImageRenderer(new RendererStyle(256, 0), new SvgImageBackEnd()));
        $entries = [];
        foreach ($ids as $id) {
            $record = $records->get($id);
            $templateId = $record->version->template_id;
            $template = $templates->get($templateId);
            abort_unless($template, 404);
            $fields = $resolver->forIssue($record);
            $entries[] = [
                'record' => $record,
                'template' => $template,
                'fields' => $fields,
                'qrSvg' => $writer->writeString($fields['verification_url']),
                'assets' => ($assetsByTemplate->get($templateId) ?? collect())->keyBy('id'),
            ];
        }

        return response()->view('studio.cards.bulk-print', compact('entries'))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function print(Request $request, int $issue, CardFieldResolver $resolver)
    {
        $company=$this->viewAccess($request);
        $record=StudioCardIssue::where('company_id',$company)->with(['employee','version'])->findOrFail($issue);
        $template=StudioTemplate::where('company_id',$company)->findOrFail($record->version->template_id);
        $assets=StudioAsset::where('company_id',$company)->where('template_id',$template->id)->get()->keyBy('id');
        $fields=$resolver->forIssue($record);
        $qrSvg=(new Writer(new ImageRenderer(new RendererStyle(256, 0), new SvgImageBackEnd())))->writeString($fields['verification_url']);
        return response()->view('studio.cards.print', compact('record','template','assets','fields','qrSvg'))
            ->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff')
            ->header('Referrer-Policy','no-referrer');
    }

    public function revoke(Request $request, int $issue, StudioAudit $audit)
    {
        $company=$this->writeAccess($request);
        $request->validate(['reason'=>['required','string','min:5','max:240']]);
        $employeeId=DB::transaction(function () use ($request,$company,$issue,$audit) {
            $record=StudioCardIssue::where('company_id',$company)->lockForUpdate()->findOrFail($issue);
            if ($record->status !== 'active') { abort(409,'Card already revoked.'); }
            $record->status='revoked';$record->revoked_at=now();$record->revocation_reason=$request->input('reason');$record->save();
            // Only field names recorded in audit; revocation reason belongs in protected support workflow.
            $audit->record($request->user(),'card.revoked',$record,['status','revoked_at','reason_supplied']);
            return $record->employee_id;
        });
        return redirect()->route('studio.cards.form',$employeeId)->with('status','ID revoked. QR no longer validates.');
    }

    public function issuePhoto(Request $request, int $issue)
    {
        $company=$this->viewAccess($request);
        $record=StudioCardIssue::where('company_id',$company)->findOrFail($issue);
        abort_unless($record->photo_path_snapshot && Storage::disk('local')->exists($record->photo_path_snapshot),404);
        return Storage::disk('local')->response($record->photo_path_snapshot,null,['Cache-Control'=>'private, max-age=120','X-Content-Type-Options'=>'nosniff']);
    }

    public function verify(Request $request, string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{43}$/D',$token)===1,404);
        $record=StudioCardIssue::where('token_hash',hash('sha256',$token))->with(['employee'])->first();
        $valid=$record && $record->status==='active' && $record->employee?->status==='active'
            && (!$record->expires_at || $record->expires_at->endOfDay()->isFuture());
        return response()->view('studio.cards.verify',[
            'valid'=>$valid,'company'=>$valid?StudioCompany::find($record->company_id)?->name:null,
            'employeeCode'=>$valid?$record->display_snapshot['employee_code']:null,
            'employeeName'=>$valid?$record->display_snapshot['full_name']:null,
        ])->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer')
          ->header('X-Robots-Tag','noindex, nofollow');
    }
}
