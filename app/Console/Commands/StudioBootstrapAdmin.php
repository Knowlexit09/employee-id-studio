<?php
namespace App\Console\Commands;

use App\Models\StudioCompany;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StudioBootstrapAdmin extends Command
{
    protected $signature = 'studio:bootstrap {company : Company name} {email : Administrator email}';
    protected $description = 'Provision the first company administrator securely from the server terminal';

    public function handle(): int
    {
        $companyName = trim((string)$this->argument('company'));
        $email = strtolower(trim((string)$this->argument('email')));
        if ($companyName==='' || mb_strlen($companyName)>160 || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a company name (up to 160 chars) and valid email.'); return self::FAILURE;
        }
        if (User::where('email',$email)->exists()) { $this->error('This email is already registered.'); return self::FAILURE; }
        $password=$this->secret('Set administrator password (minimum 14 characters)');
        if (!is_string($password) || mb_strlen($password)<14) { $this->error('Password too short.'); return self::FAILURE; }
        DB::transaction(function () use ($companyName,$email,$password): void {
            $company=StudioCompany::create(['uuid'=>(string)Str::uuid(),'name'=>$companyName]);
            $user=new User(['name'=>'System Administrator','email'=>$email,'password'=>$password]);
            $user->studio_company_id=$company->id;
            $user->studio_role='super_admin';
            $user->studio_active=true;
            $user->save();
        });
        $this->info('Administrator created. No default credentials were shipped.');
        return self::SUCCESS;
    }
}
