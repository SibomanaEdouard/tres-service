<?php

namespace App\Http\Controllers\Api;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MoveCopyController extends BaseController
{
    /**
     * @OA\Post(
     * path="/api/v1/move",
     * summary="Move file(s) or folder(s)",
     * description="Moves one or more files or folders to a specified destination folder",
     * operationId="moveItems",
     * tags={"File Listing & Pagination"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"destination_folder_id"},
     * @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"), nullable=true, example={1, 2}),
     * @OA\Property(property="folder_ids", type="array", @OA\Items(type="integer"), nullable=true, example={10, 11}),
     * @OA\Property(property="destination_folder_id", type="integer", description="ID of the destination folder", example=5)
     * )
     * ),
     * @OA\Response(response=200, description="Items moved successfully"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Destination folder or one or more items not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function move(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_ids' => 'array',
            'file_ids.*' => 'integer|exists:files,id',
            'folder_ids' => 'array',
            'folder_ids.*' => 'integer|exists:folders,id',
            'destination_folder_id' => 'required|exists:folders,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = auth('api')->user();
        $destinationFolder = Folder::where('user_id', $user->id)->find($request->destination_folder_id);

        if (!$destinationFolder) {
            return $this->sendError('Destination folder not found or not owned by user.', [], 404);
        }

        $movedCount = 0;

        // Move files
        if ($request->has('file_ids')) {
            $filesToMove = File::where('user_id', $user->id)
                               ->whereIn('id', $request->file_ids)
                               ->get();
            foreach ($filesToMove as $file) {
                // Logic to move the actual file in storage (optional, as path is in DB)
                // For simplicity, we just update the folder_id in DB
                $file->folder_id = $destinationFolder->id;
                $file->save();
                $movedCount++;
            }
        }

        // Move folders
        if ($request->has('folder_ids')) {
            $foldersToMove = Folder::where('user_id', $user->id)
                                   ->whereIn('id', $request->folder_ids)
                                   ->get();
            foreach ($foldersToMove as $folder) {
                // Ensure a folder cannot be moved into itself or its subfolder
                if ($folder->id === $destinationFolder->id || $folder->isDescendantOf($destinationFolder->id)) {
                    // Skip or return error for invalid move
                    continue;
                }
                $folder->parent_id = $destinationFolder->id;
                $folder->save();
                $movedCount++;
            }
        }

        return $this->sendResponse([], 'Items moved successfully');
    }

    /**
     * @OA\Post(
     * path="/api/v1/copy",
     * summary="Duplicate file(s) or folder(s)",
     * description="Duplicates one or more files or folders to a specified destination folder",
     * operationId="copyItems",
     * tags={"File Listing & Pagination"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"destination_folder_id"},
     * @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"), nullable=true, example={1, 2}),
     * @OA\Property(property="folder_ids", type="array", @OA\Items(type="integer"), nullable=true, example={10, 11}),
     * @OA\Property(property="destination_folder_id", type="integer", description="ID of the destination folder", example=5)
     * )
     * ),
     * @OA\Response(response=200, description="Items copied successfully"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Destination folder or one or more items not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function copy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_ids' => 'array',
            'file_ids.*' => 'integer|exists:files,id',
            'folder_ids' => 'array',
            'folder_ids.*' => 'integer|exists:folders,id',
            'destination_folder_id' => 'required|exists:folders,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = auth('api')->user();
        $destinationFolder = Folder::where('user_id', $user->id)->find($request->destination_folder_id);

        if (!$destinationFolder) {
            return $this->sendError('Destination folder not found or not owned by user.', [], 404);
        }

        $copiedCount = 0;

        // Copy files
        if ($request->has('file_ids')) {
            $filesToCopy = File::where('user_id', $user->id)
                               ->whereIn('id', $request->file_ids)
                               ->get();
            foreach ($filesToCopy as $file) {
                $newPath = Storage::copy($file->file_path, 'users/' . $user->id . '/folders/' . $destinationFolder->id . '/' . Str::random(10) . '_' . $file->name);
                if ($newPath) {
                    File::create([
                        'user_id' => $user->id,
                        'folder_id' => $destinationFolder->id,
                        'name' => 'Copy of ' . $file->name,
                        'description' => $file->description,
                        'file_path' => $newPath,
                        'file_type' => $file->file_type,
                        'file_size' => $file->file_size,
                        'total_downloads' => 0, // Reset downloads for copy
                    ]);
                    $copiedCount++;
                }
            }
        }

        // Copy folders
        if ($request->has('folder_ids')) {
            $foldersToCopy = Folder::where('user_id', $user->id)
                                   ->whereIn('id', $request->folder_ids)
                                   ->get();
            foreach ($foldersToCopy as $folder) {
                // This is a simplified copy. For deep copy (recursive), you'd need a dedicated service.
                $newFolder = Folder::create([
                    'user_id' => $user->id,
                    'parent_id' => $destinationFolder->id,
                    'name' => 'Copy of ' . $folder->name,
                    'privacy' => $folder->privacy,
                    'password' => $folder->password, // Retain password if applicable
                    'allow_download' => $folder->allow_download,
                    'watermark_images' => $folder->watermark_images,
                ]);
                $copiedCount++;

                // Optionally, recursively copy files/subfolders within the copied folder
                // This would involve more complex logic or a recursive function.
            }
        }

        return $this->sendResponse([], 'Items copied successfully');
    }
}