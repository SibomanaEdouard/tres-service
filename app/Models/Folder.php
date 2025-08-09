<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // For trash functionality

/**
 * @OA\Schema(
 * schema="Folder",
 * type="object",
 * title="Folder",
 * description="Folder model",
 * required={"id", "user_id", "name", "privacy", "created_at", "updated_at"},
 * @OA\Property(property="id", type="integer", format="int64", description="Folder ID", example=1),
 * @OA\Property(property="user_id", type="integer", format="int64", description="Owner User ID", example=1),
 * @OA\Property(property="parent_id", type="integer", format="int64", nullable=true, description="Parent folder ID", example=null),
 * @OA\Property(property="name", type="string", description="Folder name", example="My Documents"),
 * @OA\Property(property="privacy", type="string", enum={"public", "private"}, description="Folder privacy", example="private"),
 * @OA\Property(property="password_protected", type="boolean", description="Is folder password protected?", example=false),
 * @OA\Property(property="allow_download", type="boolean", description="Allow download from this folder", example=true),
 * @OA\Property(property="watermark_images", type="boolean", description="Watermark images in this folder", example=false),
 * @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp", example="2025-08-09T12:00:00Z"),
 * @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp", example="2025-08-09T12:00:00Z"),
 * @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, description="Deletion timestamp (for soft delete)", example=null)
 * )
 */
class Folder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'privacy',
        'password',
        'allow_download',
        'watermark_images',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password_protected' => 'boolean',
        'allow_download' => 'boolean',
        'watermark_images' => 'boolean',
    ];

    /**
     * Get the user that owns the folder.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent folder.
     */
    public function parent()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * Get the subfolders.
     */
    public function subfolders()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    /**
     * Get the files in the folder.
     */
    public function files()
    {
        return $this->hasMany(File::class);
    }

    /**
     * Get the share links for the folder.
     */
    public function shareLinks()
    {
        return $this->morphMany(ShareLink::class, 'shareable');
    }
}