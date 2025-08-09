<?php

namespace App\Http\Controllers\Api;

use App\Models\User; // Assuming settings are part of the User model or a related profile model
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AccountSettingsController extends BaseController
{
    /**
     * @OA\Get(
     * path="/api/v1/account/settings",
     * summary="Get current account settings",
     * description="Retrieves the account settings for the authenticated user",
     * operationId="getAccountSettings",
     * tags={"Account Settings"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Account settings retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Account settings retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="default_privacy", type="string", enum={"private","public"}, example="private"),
     * @OA\Property(property="avatar_url", type="string", nullable=true, example="https://example.com/avatars/user_1.png"),
     * @OA\Property(property="enable_watermark", type="boolean", example=true),
     * @OA\Property(property="watermark_position", type="string", enum={"top-left","top-right","bottom-left","bottom-right"}, nullable=true, example="bottom-right")
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        // Assuming these settings are stored directly on the User model
        // You might need to add these fields to your User model migration
        $settings = [
            'default_privacy' => $user->default_privacy ?? 'private',
            'avatar_url' => $user->avatar ? Storage::url($user->avatar) : null,
            'enable_watermark' => $user->enable_watermark ?? false,
            'watermark_position' => $user->watermark_position ?? null,
        ];

        return $this->sendResponse($settings, 'Account settings retrieved successfully');
    }

    /**
     * @OA\Patch(
     * path="/api/v1/account/settings",
     * summary="Update account settings",
     * description="Updates the account settings for the authenticated user",
     * operationId="updateAccountSettings",
     * tags={"Account Settings"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * @OA\Property(property="default_privacy", type="string", enum={"private","public"}, nullable=true, example="public"),
     * @OA\Property(property="avatar", type="string", format="binary", nullable=true, description="New avatar image file"),
     * @OA\Property(property="enable_watermark", type="boolean", nullable=true, example=true),
     * @OA\Property(property="watermark_position", type="string", enum={"top-left","top-right","bottom-left","bottom-right"}, nullable=true, example="top-left")
     * )
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Account settings updated successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Account settings updated successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="default_privacy", type="string", enum={"private","public"}, example="private"),
     * @OA\Property(property="avatar_url", type="string", nullable=true, example="https://example.com/avatars/user_1.png"),
     * @OA\Property(property="enable_watermark", type="boolean", example=true),
     * @OA\Property(property="watermark_position", type="string", enum={"top-left","top-right","bottom-left","bottom-right"}, nullable=true, example="bottom-right")
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'default_privacy' => 'sometimes|in:public,private',
            'avatar' => 'nullable|image|max:2048', // Max 2MB
            'enable_watermark' => 'sometimes|boolean',
            'watermark_position' => 'nullable|in:top-left,top-right,bottom-left,bottom-right',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::delete($user->avatar);
            }
            $avatarPath = $request->file('avatar')->store("avatars/{$user->id}");
            $user->avatar = $avatarPath;
        }

        $user->fill($request->only('default_privacy', 'enable_watermark', 'watermark_position'));
        $user->save();

        // Return updated settings
        $settings = [
            'default_privacy' => $user->default_privacy,
            'avatar_url' => $user->avatar ? Storage::url($user->avatar) : null,
            'enable_watermark' => $user->enable_watermark,
            'watermark_position' => $user->watermark_position,
        ];

        return $this->sendResponse($settings, 'Account settings updated successfully');
    }
}