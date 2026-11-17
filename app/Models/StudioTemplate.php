<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StudioTemplate extends Model
{
    protected $table = 'studio_templates';
    protected $guarded = ['id'];
    protected function casts(): array { return ['draft_layout' => 'array', 'archived_at' => 'datetime', 'revision'=>'integer']; }
    public function versions(): HasMany { return $this->hasMany(StudioTemplateVersion::class, 'template_id'); }
    public function assets(): HasMany { return $this->hasMany(StudioAsset::class, 'template_id'); }
}
