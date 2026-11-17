<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class StudioTemplateVersion extends Model
{
    public $timestamps = false;
    protected $table = 'studio_template_versions';
    protected $guarded = ['id'];
    protected function casts(): array { return ['layout' => 'array', 'published_at' => 'datetime']; }
}
