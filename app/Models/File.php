<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // For trash functionality

/**
 * @OA\Schema(
 * schema="File",
 * type="object",
 * title="File",
 * description="File model",
 * required={"id", "user_id", "folder_id", "name", "file_path", "file_type", "file_size", "created_at", "updated_at"},
 * @OA\Property(property="id", type="integer", format="int64", description="File ID", example=1),
 * @OA\Property(property="user_id", type="integer", format="int64", description="Owner User ID", example=1),
 * @OA\Property(property="folder_id", type="integer", format="int64", description="Parent Folder ID", example=1),
 * @OA\Property(property="name", type="string", description="File name", example="document.pdf"),
 * @OA\Property(property="description", type="string", nullable=true, description="File description", example="A brief summary of the document."),
 * @OA\Property(property="file_path", type="string", description="Internal path to the file storage", example="files/user_1/folder_1/document.pdf"),
 * @OA\Property(property="file_type", type="string", description="MIME type of the file", example="application/pdf"),
 * @OA\Property(property="file_size", type="integer", description="File size in bytes", example=1024000),
 * @OA\Property(property="total_downloads", type="integer", description="Total number of times the file has been downloaded", example=50),
 * @OA\Property(property="last_access_date", type="string", format="date-time", nullable=true, description="Last access timestamp", example="2025-08-09T14:30:00Z"),
 * @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp", example="2025-08-09T12:00:00Z"),
 * @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp", example="2025-08-09T12:00:00Z"),
 * @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, description="Deletion timestamp (for soft delete)", example=null)
 * )
 */
class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'folder_id',
        'name',
        'description',
        'file_path',
        'file_type',
        'file_size',
        'total_downloads',
        'last_access_date',
    ];

    protected $casts = [
        'total_downloads' => 'integer',
        'file_size' => 'integer',
        'last_access_date' => 'datetime',
    ];

    /**
     * Get the user that owns the file.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the folder that the file belongs to.
     */
    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Get the share links for the file.
     */
    public function shareLinks()
    {
        return $this->morphMany(ShareLink::class, 'shareable');
    }
}