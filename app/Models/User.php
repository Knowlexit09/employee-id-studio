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
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['password'=>'hashed', 'email_verified_at'=>'datetime', 'studio_active'=>'boolean']; }
    public function studioCompany(): BelongsTo { return $this->belongsTo(StudioCompany::class, 'studio_company_id'); }
    public function canManageStudio(): bool { return $this->studio_active && in_array($this->studio_role, ['super_admin', 'hr'], true); }
    public function canViewStudio(): bool { return $this->studio_active && in_array($this->studio_role, ['super_admin', 'hr', 'manager'], true); }
}
