<?php

namespace App\Http\Controllers\Api;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends BaseController
{


     /**
     * @OA\Get(
     * path="/api/v1/all-files",
     * summary="List all files with pagination",
     * description="Retrieves a paginated and sortable list of all files for the authenticated user, with optional search.",
     * operationId="getAllFiles",
     * tags={"File Listing & Pagination"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1), description="Page number"),
     * @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=20), description="Items per page"),
     * @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", default="uploaded_date", enum={"filename", "uploaded_date", "total_downloads", "filesize", "last_access_date"}), description="Field to sort results by"),
     * @OA\Parameter(name="order", in="query", @OA\Schema(type="string", default="desc", enum={"asc", "desc"}), description="Sort order: asc or desc"),
     * @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Search by filename or description"),
     * @OA\Parameter(name="folder_id", in="query", @OA\Schema(type="integer"), description="Limit results to a specific folder ID"),
     * @OA\Response(
     * response=200,
     * description="Files retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Files retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(ref="#/components/schemas/File")
     * ),
     * @OA\Property(property="pagination", type="object",
     * @OA\Property(property="total_items", type="integer", example=100),
     * @OA\Property(property="total_pages", type="integer", example=10),
     * @OA\Property(property="current_page", type="integer", example=1),
     * @OA\Property(property="limit", type="integer", example=10)
     * ),
     * @OA\Property(property="sort", type="object",
     * @OA\Property(property="by", type="string", example="uploaded_date"),
     * @OA\Property(property="order", type="string", example="desc")
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function allFiles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:name,created_at,total_downloads,file_size,last_access_date',
            'order' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string',
            'folder_id' => 'nullable|integer|exists:folders,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = auth('api')->user();
        $query = File::where('user_id', $user->id);

        // Apply folder filter
        if ($request->has('folder_id')) {
            $query->where('folder_id', $request->input('folder_id'));
        }

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at'); // Default to created_at for uploaded_date
        $order = $request->input('order', 'desc');

        // Map sort_by parameter to actual database columns
        $columnMap = [
            'filename' => 'name',
            'uploaded_date' => 'created_at',
            'total_downloads' => 'total_downloads',
            'filesize' => 'file_size',
            'last_access_date' => 'last_access_date',
        ];
        $actualSortColumn = $columnMap[$sortBy] ?? 'created_at';

        $files = $query->orderBy($actualSortColumn, $order)
                       ->paginate($request->input('limit', 20));

        return $this->sendResponse($files->items(), 'Files retrieved successfully', 200, [
            'pagination' => [
                'total_items' => $files->total(),
                'total_pages' => $files->lastPage(),
                'current_page' => $files->currentPage(),
                'limit' => $files->perPage(),
            ],
            'sort' => [
                'by' => $sortBy,
                'order' => $order,
            ]
        ]);
    }
    /**
     * @OA\Get(
     * path="/api/v1/file/{id}",
     * summary="Get file details",
     * description="Retrieves metadata and details for a specific file",
     * operationId="getFileDetails",
     * tags={"File Management"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the file", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="File details retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="File details retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/File")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="File not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $file = File::where('user_id', $user->id)->find($id);

        if (!$file) {
            return $this->sendError('File not found or not owned by user.', [], 404);
        }

        return $this->sendResponse($file, 'File details retrieved successfully');
    }

    /**
     * @OA\Patch(
     * path="/api/v1/file/{id}",
     * summary="Update file metadata",
     * description="Updates the name and/or description of a specific file",
     * operationId="updateFileMetadata",
     * tags={"File Management"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the file", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="name", type="string", nullable=true, example="Updated Document.pdf"),
     * @OA\Property(property="description", type="string", nullable=true, example="Revised description.")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="File metadata updated successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="File metadata updated successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/File")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="File not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth('api')->user();
        $file = File::where('user_id', $user->id)->find($id);

        if (!$file) {
            return $this->sendError('File not found or not owned by user.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $file->update($request->only('name', 'description'));

        return $this->sendResponse($file, 'File metadata updated successfully');
    }

    /**
     * @OA\Delete(
     * path="/api/v1/file/{id}",
     * summary="Delete a file (move to trash)",
     * description="Moves a single file to the trash using soft delete",
     * operationId="deleteSingleFile",
     * tags={"File Management"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the file", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="File moved to trash successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="File moved to trash successfully")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="File not found")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $file = File::where('user_id', $user->id)->find($id);

        if (!$file) {
            return $this->sendError('File not found or not owned by user.', [], 404);
        }

        $file->delete(); // Soft delete

        return $this->sendResponse([], 'File moved to trash successfully');
    }

    /**
     * @OA\Delete(
     * path="/api/v1/file",
     * summary="Delete multiple files (move to trash)",
     * description="Moves multiple files to the trash using soft delete",
     * operationId="deleteMultipleFiles",
     * tags={"File Management"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"file_ids"},
     * @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3})
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Files moved to trash successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Files moved to trash successfully")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="One or more files not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function deleteMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_ids' => 'required|array',
            'file_ids.*' => 'integer|exists:files,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = auth('api')->user();
        $files = File::where('user_id', $user->id)
                     ->whereIn('id', $request->file_ids)
                     ->get();

        if ($files->count() !== count($request->file_ids)) {
            return $this->sendError('One or more files not found or not owned by user.', [], 404);
        }

        File::whereIn('id', $request->file_ids)->delete(); // Soft delete

        return $this->sendResponse([], 'Files moved to trash successfully');
    }

    /**
     * @OA\Get(
     * path="/api/v1/file/download/{id}",
     * summary="Download a single file",
     * description="Downloads a specific file",
     * operationId="downloadSingleFile",
     * tags={"File Management"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the file", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="File downloaded successfully",
     * @OA\MediaType(mediaType="application/octet-stream")
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="File not found")
     * )
     */
    public function download(string $id): StreamedResponse|JsonResponse
    {
        $user = auth('api')->user();
        $file = File::where('user_id', $user->id)->find($id);

        if (!$file || !Storage::exists($file->file_path)) {
            return $this->sendError('File not found.', [], 404);
        }

        // Update download count and last access date
        $file->increment('total_downloads');
        $file->update(['last_access_date' => now()]);

        return Storage::download($file->file_path, $file->name);
    }

    /**
     * @OA\Post(
     * path="/api/v1/file/download",
     * summary="Download multiple files as ZIP",
     * description="Downloads multiple files as a single ZIP archive",
     * operationId="downloadMultipleFilesAsZip",
     * tags={"File Management"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"file_ids"},
     * @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3})
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="ZIP archive downloaded successfully",
     * @OA\MediaType(mediaType="application/zip")
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="One or more files not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function downloadMultiple(Request $request): StreamedResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_ids' => 'required|array',
            'file_ids.*' => 'integer|exists:files,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = auth('api')->user();
        $files = File::where('user_id', $user->id)
                     ->whereIn('id', $request->file_ids)
                     ->get();

        if ($files->count() === 0) {
            return $this->sendError('No files found or owned by user.', [], 404);
        }

        $zipFileName = 'files_' . now()->format('Ymd_His') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        // Ensure the temp directory exists
        if (!Storage::exists('temp')) {
            Storage::makeDirectory('temp');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $file) {
                if (Storage::exists($file->file_path)) {
                    $zip->addFile(Storage::path($file->file_path), $file->name);
                    // Update download count and last access date for each file
                    $file->increment('total_downloads');
                    $file->update(['last_access_date' => now()]);
                }
            }
            $zip->close();
        } else {
            return $this->sendError('Could not create ZIP archive.', [], 500);
        }

        return response()->streamDownload(function () use ($zipPath) {
            echo file_get_contents($zipPath);
            unlink($zipPath); // Delete the temporary zip file
        }, $zipFileName, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $zipFileName . '"',
        ]);
    }
}