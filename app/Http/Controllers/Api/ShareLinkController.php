<?php

namespace App\Http\Controllers\Api;

use App\Models\File;
use App\Models\Folder;
use App\Models\ShareLink;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage; // Added for file serving for public links

class ShareLinkController extends BaseController
{
    /**
     * @OA\Post(
     * path="/api/v1/link/file/{id}",
     * summary="Create share link for a file",
     * description="Creates a new share link for a specific file",
     * operationId="createFileShareLink",
     * tags={"Sharing & Links"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the file", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"type"},
     * @OA\Property(property="type", type="string", enum={"internal","email","public"}, example="public"),
     * @OA\Property(property="recipient_email", type="string", format="email", nullable=true, example="share@example.com"),
     * @OA\Property(property="expires_at", type="string", format="date-time", nullable=true, example="2026-08-09T12:00:00Z"),
     * @OA\Property(property="password", type="string", format="password", nullable=true, example="linkpass"),
     * @OA\Property(property="permissions", type="string", enum={"view","upload-download-view"}, example="view")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Share link created successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Share link created successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/ShareLink")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="File not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function createFileLink(Request $request, string $id): JsonResponse
    {
        $user = auth('api')->user();
        $file = File::where('user_id', $user->id)->find($id);

        if (!$file) {
            return $this->sendError('File not found or not owned by user.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:internal,email,public',
            'recipient_email' => 'nullable|email',
            'expires_at' => 'nullable|date|after:now',
            'password' => 'nullable|string|min:6',
            'permissions' => 'in:view,upload-download-view',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $linkData = $request->only('type', 'recipient_email', 'expires_at', 'permissions');
        $linkData['user_id'] = $user->id;
        $linkData['shareable_type'] = File::class;
        $linkData['shareable_id'] = $file->id;
        $linkData['link'] = url('/share/' . Str::random(32)); // Generate a unique link

        if ($request->has('password')) {
            $linkData['password'] = Hash::make($request->password);
        }

        $shareLink = ShareLink::create($linkData);

        return $this->sendResponse($shareLink, 'Share link created successfully', 201);
    }

    /**
     * @OA\Post(
     * path="/api/v1/link/folder/{id}",
     * summary="Create share link for a folder",
     * description="Creates a new share link for a specific folder",
     * operationId="createFolderShareLink",
     * tags={"Sharing & Links"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the folder", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"type"},
     * @OA\Property(property="type", type="string", enum={"internal","email","public"}, example="public"),
     * @OA\Property(property="recipient_email", type="string", format="email", nullable=true, example="share@example.com"),
     * @OA\Property(property="expires_at", type="string", format="date-time", nullable=true, example="2026-08-09T12:00:00Z"),
     * @OA\Property(property="password", type="string", format="password", nullable=true, example="linkpass"),
     * @OA\Property(property="permissions", type="string", enum={"view","upload-download-view"}, example="view")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Share link created successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Share link created successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/ShareLink")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Folder not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function createFolderLink(Request $request, string $id): JsonResponse
    {
        $user = auth('api')->user();
        $folder = Folder::where('user_id', $user->id)->find($id);

        if (!$folder) {
            return $this->sendError('Folder not found or not owned by user.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:internal,email,public',
            'recipient_email' => 'nullable|email',
            'expires_at' => 'nullable|date|after:now',
            'password' => 'nullable|string|min:6',
            'permissions' => 'in:view,upload-download-view',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $linkData = $request->only('type', 'recipient_email', 'expires_at', 'permissions');
        $linkData['user_id'] = $user->id;
        $linkData['shareable_type'] = Folder::class;
        $linkData['shareable_id'] = $folder->id;
        $linkData['link'] = url('/share/' . Str::random(32)); // Generate a unique link

        if ($request->has('password')) {
            $linkData['password'] = Hash::make($request->password);
        }

        $shareLink = ShareLink::create($linkData);

        return $this->sendResponse($shareLink, 'Share link created successfully', 201);
    }

    /**
     * @OA\Get(
     * path="/api/v1/link/file/{id}",
     * summary="Get file link details",
     * description="Retrieves details of a specific share link for a file",
     * operationId="getFileLinkDetails",
     * tags={"Sharing & Links"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the share link", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="File link details retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="File link details retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/ShareLink")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Share link not found")
     * )
     */
    public function getFileLinkDetails(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $shareLink = ShareLink::where('user_id', $user->id)
                              ->where('shareable_type', File::class)
                              ->find($id);

        if (!$shareLink) {
            return $this->sendError('Share link not found or not owned by user.', [], 404);
        }

        return $this->sendResponse($shareLink, 'File link details retrieved successfully');
    }

    /**
     * @OA\Get(
     * path="/api/v1/link/folder/{id}",
     * summary="Get folder link details",
     * description="Retrieves details of a specific share link for a folder",
     * operationId="getFolderLinkDetails",
     * tags={"Sharing & Links"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the share link", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Folder link details retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Folder link details retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/ShareLink")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Share link not found")
     * )
     */
    public function getFolderLinkDetails(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $shareLink = ShareLink::where('user_id', $user->id)
                              ->where('shareable_type', Folder::class)
                              ->find($id);

        if (!$shareLink) {
            return $this->sendError('Share link not found or not owned by user.', [], 404);
        }

        return $this->sendResponse($shareLink, 'Folder link details retrieved successfully');
    }

    /**
     * @OA\Patch(
     * path="/api/v1/link/file/{id}",
     * summary="Update file share link settings",
     * description="Updates settings of an existing share link for a file",
     * operationId="updateFileShareLink",
     * tags={"Sharing & Links"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the share link", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="type", type="string", enum={"internal","email","public"}, nullable=true, example="internal"),
     * @OA\Property(property="recipient_email", type="string", format="email", nullable=true, example="new_recipient@example.com"),
     * @OA\Property(property="expires_at", type="string", format="date-time", nullable=true, example="2027-08-09T12:00:00Z"),
     * @OA\Property(property="password", type="string", format="password", nullable=true, example="newlinkpass"),
     * @OA\Property(property="permissions", type="string", enum={"view","upload-download-view"}, nullable=true, example="upload-download-view")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="File share link updated successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="File share link updated successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/ShareLink")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Share link not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function updateFileShareLink(Request $request, string $id): JsonResponse
    {
        $user = auth('api')->user();
        $shareLink = ShareLink::where('user_id', $user->id)
                              ->where('shareable_type', File::class)
                              ->find($id);

        if (!$shareLink) {
            return $this->sendError('Share link not found or not owned by user.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:internal,email,public',
            'recipient_email' => 'nullable|email',
            'expires_at' => 'nullable|date|after:now',
            'password' => 'nullable|string|min:6', // Nullable to remove password protection
            'permissions' => 'sometimes|in:view,upload-download-view',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $data = $request->only('type', 'recipient_email', 'expires_at', 'permissions');

        if ($request->has('password')) {
            $data['password'] = $request->password ? Hash::make($request->password) : null;
        }

        $shareLink->update($data);

        return $this->sendResponse($shareLink, 'File share link updated successfully');
    }

    /**
     * @OA\Patch(
     * path="/api/v1/link/folder/{id}",
     * summary="Update folder share link settings",
     * description="Updates settings of an existing share link for a folder",
     * operationId="updateFolderShareLink",
     * tags={"Sharing & Links"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the share link", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="type", type="string", enum={"internal","email","public"}, nullable=true, example="internal"),
     * @OA\Property(property="recipient_email", type="string", format="email", nullable=true, example="new_recipient@example.com"),
     * @OA\Property(property="expires_at", type="string", format="date-time", nullable=true, example="2027-08-09T12:00:00Z"),
     * @OA\Property(property="password", type="string", format="password", nullable=true, example="newlinkpass"),
     * @OA\Property(property="permissions", type="string", enum={"view","upload-download-view"}, nullable=true, example="upload-download-view")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Folder share link updated successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Folder share link updated successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/ShareLink")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Share link not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function updateFolderShareLink(Request $request, string $id): JsonResponse
    {
        $user = auth('api')->user();
        $shareLink = ShareLink::where('user_id', $user->id)
                              ->where('shareable_type', Folder::class)
                              ->find($id);

        if (!$shareLink) {
            return $this->sendError('Share link not found or not owned by user.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:internal,email,public',
            'recipient_email' => 'nullable|email',
            'expires_at' => 'nullable|date|after:now',
            'password' => 'nullable|string|min:6',
            'permissions' => 'sometimes|in:view,upload-download-view',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $data = $request->only('type', 'recipient_email', 'expires_at', 'permissions');

        if ($request->has('password')) {
            $data['password'] = $request->password ? Hash::make($request->password) : null;
        }

        $shareLink->update($data);

        return $this->sendResponse($shareLink, 'Folder share link updated successfully');
    }

    /**
     * @OA\Delete(
     * path="/api/v1/link/file/{id}",
     * summary="Delete file share link",
     * description="Deletes an existing share link for a file",
     * operationId="deleteFileShareLink",
     * tags={"Sharing & Links"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the share link", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="File share link deleted successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="File share link deleted successfully")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Share link not found")
     * )
     */
    public function deleteFileShareLink(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $shareLink = ShareLink::where('user_id', $user->id)
                              ->where('shareable_type', File::class)
                              ->find($id);

        if (!$shareLink) {
            return $this->sendError('Share link not found or not owned by user.', [], 404);
        }

        $shareLink->delete();

        return $this->sendResponse([], 'File share link deleted successfully');
    }

    /**
     * @OA\Delete(
     * path="/api/v1/link/folder/{id}",
     * summary="Delete folder share link",
     * description="Deletes an existing share link for a folder",
     * operationId="deleteFolderShareLink",
     * tags={"Sharing & Links"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the share link", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Folder share link deleted successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Folder share link deleted successfully")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Share link not found")
     * )
     */
    public function deleteFolderShareLink(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $shareLink = ShareLink::where('user_id', $user->id)
                              ->where('shareable_type', Folder::class)
                              ->find($id);

        if (!$shareLink) {
            return $this->sendError('Share link not found or not owned by user.', [], 404);
        }

        $shareLink->delete();

        return $this->sendResponse([], 'Folder share link deleted successfully');
    }

    /**
     * @OA\Get(
     * path="/api/v1/shared-with-me",
     * summary="Get folders & files shared with the current user",
     * description="Retrieves a list of files and folders shared with the authenticated user (via email share or internal share)",
     * operationId="getSharedWithMe",
     * tags={"Sharing & Links"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Shared items retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Shared items retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="shared_files", type="array", @OA\Items(ref="#/components/schemas/File")),
     * @OA\Property(property="shared_folders", type="array", @OA\Items(ref="#/components/schemas/Folder"))
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getSharedWithMe(): JsonResponse
    {
        $user = auth('api')->user();

        // Get links where the current user is the recipient_email
        $sharedLinks = ShareLink::where('recipient_email', $user->email)
                                ->whereNull('expires_at') // Not expired
                                ->orWhere('expires_at', '>', now())
                                ->get();

        $sharedFiles = collect();
        $sharedFolders = collect();

        foreach ($sharedLinks as $link) {
            if ($link->shareable_type === File::class) {
                $file = File::find($link->shareable_id);
                if ($file) {
                    $sharedFiles->push($file);
                }
            } elseif ($link->shareable_type === Folder::class) {
                $folder = Folder::find($link->shareable_id);
                if ($folder) {
                    $sharedFolders->push($folder);
                }
            }
        }

        return $this->sendResponse([
            'shared_files' => $sharedFiles->unique('id')->values(),
            'shared_folders' => $sharedFolders->unique('id')->values(),
        ], 'Shared items retrieved successfully');
    }
}