<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;


/**
 * @OA\Schema(
 * schema="User",
 * type="object",
 * title="User",
 * description="User model",
 * required={"id", "firstname", "lastname", "email", "username","created_at", "updated_at"},
 * @OA\Property(
 * property="id",
 * type="integer",
 * format="int64",
 * description="User ID",
 * example=1
 * ),
 * @OA\Property(
 * property="firstname",
 * type="string",
 * description="User's first name",
 * example="Edouard"
 * ),
 * @OA\Property(
 * property="lastname",
 * type="string",
 * description="User's last name",
 * example="Doe"
 * ),
 * @OA\Property(
 * property="email",
 * type="string",
 * format="email",
 * description="User email",
 * example="edouard@example.com"
 * ),
 * @OA\Property(
 * property="username",
 * type="string",
 * description="username",
 * example="edouard"
 * ),
 * @OA\Property(
 * property="phone",
 * type="string",
 * nullable=true,
 * description="User phone number",
 * example="+1234567890"
 * ),
 * @OA\Property(
 * property="avatar",
 * type="string",
 * nullable=true,
 * description="URL to user's avatar image",
 * example="https://example.com/avatars/edouard.png"
 * ),
 * @OA\Property(
 * property="default_privacy",
 * type="string",
 * enum={"private", "public"},
 * description="Default privacy setting for new files/folders",
 * example="private"
 * ),
 * @OA\Property(
 * property="enable_watermark",
 * type="boolean",
 * description="Flag to enable image watermarking by default",
 * example=false
 * ),
 * @OA\Property(
 * property="watermark_position",
 * type="string",
 * enum={"top-left", "top-right", "bottom-left", "bottom-right"},
 * nullable=true,
 * description="Default position for image watermark",
 * example="bottom-right"
 * ),
 * @OA\Property(
 * property="email_verified_at",
 * type="string",
 * format="date-time",
 * description="Email verification timestamp",
 * example="2023-01-01T12:00:00Z",
 * nullable=true
 * ),
 * @OA\Property(
 * property="created_at",
 * type="string",
 * format="date-time",
 * description="Creation timestamp",
 * example="2023-01-01T12:00:00Z"
 * ),
 * @OA\Property(
 * property="updated_at",
 * type="string",
 * format="date-time",
 * description="Last update timestamp",
 * example="2023-01-01T12:00:00Z"
 * )
 * )
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'firstname', // Retained
        'lastname', // Retained
        'email',
        'password',
        'username',
        'phone', // Added for account settings
        'avatar', // Added for account settings
        'default_privacy', // Added for account settings
        'enable_watermark', // Added for account settings
        'watermark_position', // Added for account settings
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'enable_watermark' => 'boolean', // Cast to boolean for settings
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}