<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class StudioEmployee extends Model
{
    protected $table = 'studio_employees';
    protected $guarded = ['id', 'company_id', 'uuid', 'photo_path'];

    protected function casts(): array
    {
        return [
            'sss_number' => 'encrypted', 'philhealth_number' => 'encrypted',
            'pagibig_mid' => 'encrypted', 'tin' => 'encrypted',
            'emergency_name' => 'encrypted', 'emergency_relationship' => 'encrypted',
            'emergency_phone' => 'encrypted', 'hire_date' => 'date:Y-m-d',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])));
    }
}
