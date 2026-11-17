<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class StudioCardIssue extends Model
{
    protected $table = 'studio_card_issues';
    protected $guarded = ['id'];
    protected $hidden = ['token_encrypted', 'token_hash', 'revocation_reason'];
    protected function casts(): array { return ['token_encrypted' => 'encrypted', 'revocation_reason' => 'encrypted', 'display_snapshot' => 'array', 'issued_at' => 'datetime', 'revoked_at' => 'datetime', 'expires_at' => 'date']; }
    public function employee(): BelongsTo { return $this->belongsTo(StudioEmployee::class, 'employee_id'); }
    public function version(): BelongsTo { return $this->belongsTo(StudioTemplateVersion::class, 'version_id'); }
}
