<?php
namespace App\Http\Controllers;

use App\Http\Requests\StudioEmployeeRequest;
use App\Models\StudioEmployee;
use App\Services\StudioAudit;
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

    public function store(StudioEmployeeRequest $request, StudioAudit $audit)
    {
        $company = $this->writeAccess($request);
        $data = $request->safe()->except('photo');
        $photo = $request->file('photo');
        $record = DB::transaction(function () use ($request, $data, $company, $photo, $audit) {
            $employee = new StudioEmployee();
            $employee->company_id = $company;
            $employee->uuid = (string) Str::uuid();
            $employee->fill($data);
            if ($photo) { $employee->photo_path = $photo->store("studio/{$company}/employee-photos",'local'); }
            $employee->save();
            $audit->record($request->user(), 'employee.created', $employee, array_keys($data));
            return $employee;
        });
        return redirect()->route('studio.employees.edit', $record->id)->with('status','Employee created.');
    }

    public function edit(Request $request, int $employee)
    {
        $company=$this->writeAccess($request);
        return view('studio.employees.form', ['employee'=>StudioEmployee::where('company_id',$company)->findOrFail($employee)]);
    }

    public function update(StudioEmployeeRequest $request, int $employee, StudioAudit $audit)
    {
        $company=$this->writeAccess($request);
        $data=$request->safe()->except('photo');
        DB::transaction(function () use ($company,$request,$employee,$data,$audit) {
            $record=StudioEmployee::where('company_id',$company)->lockForUpdate()->findOrFail($employee);
            $record->fill($data);
            if ($request->hasFile('photo')) {
                // Previous paths remain retained because issued ID snapshots can reference them.
                $record->photo_path=$request->file('photo')->store("studio/{$company}/employee-photos",'local');
            }
            $fields=array_keys($record->getDirty());
            $record->save();
            if ($fields) { $audit->record($request->user(),'employee.updated',$record,$fields); }
        });
        return back()->with('status','Employee updated.');
    }

    public function photo(Request $request, int $employee)
    {
        $company=$this->viewAccess($request);
        $record=StudioEmployee::where('company_id',$company)->findOrFail($employee);
        abort_unless($record->photo_path && Storage::disk('local')->exists($record->photo_path),404);
        return Storage::disk('local')->response($record->photo_path,null,['Cache-Control'=>'private, max-age=120','X-Content-Type-Options'=>'nosniff']);
    }
}
