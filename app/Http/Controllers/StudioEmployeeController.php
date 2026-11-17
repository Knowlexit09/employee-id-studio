<?php
namespace App\Http\Controllers;

use App\Http\Requests\StudioEmployeeRequest;
use App\Models\StudioEmployee;
use App\Services\StudioAudit;
use App\Services\StudioPhotoProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StudioEmployeeController extends StudioController
{
    public function index(Request $request)
    {
        $company = $this->viewAccess($request);
        $filter = $request->validate(['q'=>['nullable','string','max:60']]);
        $q = trim($filter['q'] ?? '');
        $query = StudioEmployee::where('company_id',$company)->orderBy('last_name')->orderBy('first_name');
        if ($q !== '') {
            $query->where(fn ($query) => $query->where('employee_code','like',$q.'%')->orWhere('last_name','like',$q.'%'));
        }
        return view('studio.employees.index', ['employees'=>$query->paginate(15)->withQueryString(),'q'=>$q]);
    }

    public function create(Request $request)
    {
        $this->writeAccess($request);
        return view('studio.employees.form', ['employee'=>null]);
    }

    public function store(StudioEmployeeRequest $request, StudioAudit $audit, StudioPhotoProcessor $photos)
    {
        $company = $this->writeAccess($request);
        $data = $this->protectedData($request, false);
        $photoPath = $request->hasFile('photo') ? $photos->store($request->file('photo'), $company) : null;
        try {
            $record = DB::transaction(function () use ($request, $data, $company, $photoPath, $audit) {
            $employee = new StudioEmployee();
            $employee->company_id = $company;
            $employee->uuid = (string) Str::uuid();
            $employee->fill($data);
            if ($photoPath) { $employee->photo_path = $photoPath; }
            $employee->save();
            $audit->record($request->user(), 'employee.created', $employee, array_keys($data));
            return $employee;
            });
        } catch (\Throwable $exception) {
            if ($photoPath) { Storage::disk('local')->delete($photoPath); }
            throw $exception;
        }
        return redirect()->route('studio.employees.edit', $record->id)->with('status','Employee created.');
    }

    public function edit(Request $request, int $employee)
    {
        $company=$this->writeAccess($request);
        return view('studio.employees.form', ['employee'=>StudioEmployee::where('company_id',$company)->findOrFail($employee)]);
    }

    public function update(StudioEmployeeRequest $request, int $employee, StudioAudit $audit, StudioPhotoProcessor $photos)
    {
        $company=$this->writeAccess($request);
        $data=$this->protectedData($request, true);
        // Check company ownership before writing uploaded photo bytes.
        StudioEmployee::where('company_id',$company)->findOrFail($employee);
        $photoPath = $request->hasFile('photo') ? $photos->store($request->file('photo'), $company) : null;
        try {
            DB::transaction(function () use ($company,$request,$employee,$data,$photoPath,$audit) {
            $record=StudioEmployee::where('company_id',$company)->lockForUpdate()->findOrFail($employee);
            $record->fill($data);
            if ($photoPath) {
                // Keep old photos because historical ID snapshots can still reference them.
                $record->photo_path=$photoPath;
            }
            $fields=array_keys($record->getDirty());
            if ($fields) {
                $record->save();
                $audit->record($request->user(),'employee.updated',$record,$fields);
            }
            });
        } catch (\Throwable $exception) {
            if ($photoPath) { Storage::disk('local')->delete($photoPath); }
            throw $exception;
        }
        return back()->with('status','Employee updated.');
    }

    /**
     * Blank protected fields never clear encrypted values on update.
     * Explicit clear flags are separately validated and never mass-assigned.
     */
    private function protectedData(StudioEmployeeRequest $request, bool $updating): array
    {
        $fields = ['sss_number','philhealth_number','pagibig_mid','tin'];
        $flags = array_map(fn (string $field): string => 'clear_'.$field, $fields);
        $data = $request->safe()->except(array_merge(['photo'], $flags));
        foreach ($fields as $field) {
            if ($updating && $request->boolean('clear_'.$field)) {
                $data[$field] = null;
            } elseif (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                unset($data[$field]);
            }
        }
        return $data;
    }

    public function photo(Request $request, int $employee)
    {
        $company=$this->viewAccess($request);
        $record=StudioEmployee::where('company_id',$company)->findOrFail($employee);
        abort_unless($record->photo_path && Storage::disk('local')->exists($record->photo_path),404);
        return Storage::disk('local')->response($record->photo_path,null,['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'none'; sandbox"]);
    }
}
