<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class UserButtonPermission extends Model
{
    protected $fillable = ['user_id', 'button_key', 'is_allowed'];
    protected function casts(): array { return ['is_allowed' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
