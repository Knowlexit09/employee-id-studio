<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StudioEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->canManageStudio() === true; }
    public function rules(): array
    {
        $company = (int) $this->user()->studio_company_id;
        $employee = $this->route('employee');
        return [
            'employee_code'=>['required','string','max:48','regex:/^[a-zA-Z0-9_.-]+$/D',Rule::unique('studio_employees','employee_code')->where('company_id',$company)->ignore($employee)],
            'first_name'=>['required','string','max:100'],'middle_name'=>['nullable','string','max:100'],
            'last_name'=>['required','string','max:100'],'suffix'=>['nullable','string','max:20'],
            'job_title'=>['nullable','string','max:120'],'department'=>['nullable','string','max:120'],
            'branch'=>['nullable','string','max:120'],'email'=>['nullable','email','max:180'],
            'phone'=>['nullable','string','max:30'],'hire_date'=>['nullable','date','before_or_equal:today'],
            'status'=>['required',Rule::in(['active','inactive'])],
            'sss_number'=>['nullable','string','max:32','regex:/^[0-9-]+$/D'],
            'philhealth_number'=>['nullable','string','max:32','regex:/^[0-9-]+$/D'],
            'pagibig_mid'=>['nullable','string','max:32','regex:/^[0-9-]+$/D'],
            'tin'=>['nullable','string','max:32','regex:/^[0-9-]+$/D'],
            'emergency_name'=>['nullable','string','max:120'],
            'emergency_relationship'=>['nullable','string','max:80'],
            'emergency_phone'=>['nullable','string','max:25','regex:/^[0-9+ ()-]+$/D','required_with:emergency_name'],
            'photo'=>['nullable','image','mimes:jpeg,png,webp','max:2048','dimensions:max_width=4096,max_height=4096'],
        ];
    }
}
