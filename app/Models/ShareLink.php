<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 * schema="ShareLink",
 * type="object",
 * title="ShareLink",
 * description="Share link model for files and folders",
 * required={"id", "user_id", "shareable_type", "shareable_id", "type", "link", "created_at", "updated_at"},
 * @OA\Property(property="id", type="integer", format="int64", description="Share Link ID", example=1),
 * @OA\Property(property="user_id", type="integer", format="int64", description="Creator User ID", example=1),
 * @OA\Property(property="shareable_type", type="string", description="Type of shared item (e.g., App\\Models\\File, App\\Models\\Folder)", example="App\\Models\\File"),
 * @OA\Property(property="shareable_id", type="integer", format="int64", description="ID of the shared item", example=1),
 * @OA\Property(property="type", type="string", enum={"internal", "email", "public"}, description="Type of share link", example="public"),
 * @OA\Property(property="link", type="string", description="Generated share URL", example="https://example.com/share/abcdef123"),
 * @OA\Property(property="recipient_email", type="string", format="email", nullable=true, description="Recipient email for email share", example="recipient@example.com"),
 * @OA\Property(property="expires_at", type="string", format="date-time", nullable=true, description="Expiry date of the link", example="2025-09-09T12:00:00Z"),
 * @OA\Property(property="password_protected", type="boolean", description="Is link password protected?", example=false),
 * @OA\Property(property="permissions", type="string", enum={"view", "upload-download-view"}, description="Permissions for the link", example="view"),
 * @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp", example="2025-08-09T12:00:00Z"),
 * @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp", example="2025-08-09T12:00:00Z")
 * )
 */
class ShareLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shareable_type',
        'shareable_id',
        'type',
        'link',
        'recipient_email',
        'expires_at',
        'password',
        'permissions',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'password_protected' => 'boolean',
    ];

    /**
     * Get the shareable item (file or folder).
     */
    public function shareable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created the share link.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}