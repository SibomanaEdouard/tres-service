<?php

namespace App\Http\Controllers\Api;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TrashController extends BaseController
{
    /**
     * @OA\Get(
     * path="/api/v1/trash",
     * summary="List all trashed files & folders",
     * description="Retrieves a list of all soft-deleted files and folders for the authenticated user",
     * operationId="listTrash",
     * tags={"Trash & Restore"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Trashed items retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Trashed items retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="files", type="array", @OA\Items(ref="#/components/schemas/File")),
     * @OA\Property(property="folders", type="array", @OA\Items(ref="#/components/schemas/Folder"))
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $trashedFiles = File::onlyTrashed()->where('user_id', $user->id)->get();
        $trashedFolders = Folder::onlyTrashed()->where('user_id', $user->id)->get();

        return $this->sendResponse([
            'files' => $trashedFiles,
            'folders' => $trashedFolders,
        ], 'Trashed items retrieved successfully');
    }

    /**
     * @OA\Post(
     * path="/api/v1/trash/restore",
     * summary="Restore a file or folder from trash",
     * description="Restores one or more files or folders from the trash. If restoring a folder, its contents are also restored.",
     * operationId="restoreTrashItem",
     * tags={"Trash & Restore"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"), nullable=true, example={1, 2}),
     * @OA\Property(property="folder_ids", type="array", @OA\Items(type="integer"), nullable=true, example={1, 2})
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Selected items restored successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Selected items restored successfully")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="One or more items not found in trash"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function restore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_ids' => 'array',
            'file_ids.*' => 'integer|exists:files,id',
            'folder_ids' => 'array',
            'folder_ids.*' => 'integer|exists:folders,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = auth('api')->user();
        $restoredCount = 0;

        // Restore files
        if ($request->has('file_ids')) {
            $filesToRestore = File::onlyTrashed()
                                  ->where('user_id', $user->id)
                                  ->whereIn('id', $request->file_ids)
                                  ->get();
            $restoredCount += $filesToRestore->each->restore()->count();
        }

        // Restore folders and their contents
        if ($request->has('folder_ids')) {
            $foldersToRestore = Folder::onlyTrashed()
                                      ->where('user_id', $user->id)
                                      ->whereIn('id', $request->folder_ids)
                                      ->get();
            foreach ($foldersToRestore as $folder) {
                $folder->restore(); // Restore the folder
                $folder->files()->withTrashed()->restore(); // Restore files within the folder
                $folder->subfolders()->withTrashed()->restore(); // Restore subfolders (recursive restore might be complex)
                $restoredCount++;
            }
        }

        if ($restoredCount === 0 && (empty($request->file_ids) && empty($request->folder_ids))) {
             return $this->sendError('No items specified for restoration.', [], 422);
        }

        return $this->sendResponse([], 'Selected items restored successfully');
    }

    /**
     * @OA\Delete(
     * path="/api/v1/trash/file/{id}",
     * summary="Permanently delete single file from trash",
     * description="Permanently deletes a single file that is currently in the trash",
     * operationId="permanentlyDeleteFile",
     * tags={"Trash & Restore"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the file in trash", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="File permanently deleted successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="File permanently deleted successfully")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="File not found in trash")
     * )
     */
    public function deleteFilePermanently(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $file = File::onlyTrashed()->where('user_id', $user->id)->find($id);

        if (!$file) {
            return $this->sendError('File not found in trash or not owned by user.', [], 404);
        }

        // Permanently delete the file from storage
        Storage::delete($file->file_path);
        $file->forceDelete(); // Permanently delete from database

        return $this->sendResponse([], 'File permanently deleted successfully');
    }

    /**
     * @OA\Delete(
     * path="/api/v1/trash/file/all",
     * summary="Empty trash (all files/folders)",
     * description="Permanently deletes all files and folders in the trash for the authenticated user",
     * operationId="emptyTrash",
     * tags={"Trash & Restore"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Trash emptied successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Trash emptied successfully")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function emptyTrash(): JsonResponse
    {
        $user = auth('api')->user();

        // Get all trashed files and delete them from storage and database
        $trashedFiles = File::onlyTrashed()->where('user_id', $user->id)->get();
        foreach ($trashedFiles as $file) {
            if (Storage::exists($file->file_path)) {
                Storage::delete($file->file_path);
            }
            $file->forceDelete();
        }

        // Get all trashed folders and permanently delete them
        // Note: forceDeleting a folder might not forceDelete its contents
        Folder::onlyTrashed()->where('user_id', $user->id)->forceDelete();

        return $this->sendResponse([], 'Trash emptied successfully');
    }
}