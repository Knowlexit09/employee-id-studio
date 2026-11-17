<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use Notifiable, HasFactory;
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token', 'studio_mfa_secret', 'studio_mfa_recovery_hashes'];
    protected function casts(): array { return ['password'=>'hashed', 'email_verified_at'=>'datetime', 'studio_active'=>'boolean', 'studio_mfa_secret'=>'encrypted', 'studio_mfa_recovery_hashes'=>'array', 'studio_mfa_last_counter'=>'integer', 'studio_mfa_enabled_at'=>'datetime']; }
    public function studioCompany(): BelongsTo { return $this->belongsTo(StudioCompany::class, 'studio_company_id'); }
    public function canManageStudio(): bool { return $this->studio_active && in_array($this->studio_role, ['super_admin', 'hr'], true); }
    public function canDesignStudio(): bool { return $this->canManageStudio(); }
    public function canApproveStudioTemplate(): bool { return $this->studio_active && in_array($this->studio_role, ['super_admin', 'manager'], true); }
    public function canIssueStudioCard(): bool { return $this->canManageStudio(); }
    public function canViewStudio(): bool { return $this->studio_active && in_array($this->studio_role, ['super_admin', 'hr', 'manager'], true); }
}
