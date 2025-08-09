<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller; 
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use App\Models\File;

class FolderController extends BaseController 
{

        /**
     * @OA\Get(
     * path="/api/v1/all-folders",
     * summary="List all folders with pagination",
     * description="Retrieves a paginated and sortable list of all folders for the authenticated user, with optional search.",
     * operationId="getAllFolders",
     * tags={"File Listing & Pagination"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1), description="Page number"),
     * @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=20), description="Items per page"),
     * @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", default="uploaded_date", enum={"filename", "uploaded_date"}), description="Field to sort results by"),
     * @OA\Parameter(name="order", in="query", @OA\Schema(type="string", default="desc", enum={"asc", "desc"}), description="Sort order: asc or desc"),
     * @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Search by folder name"),
     * @OA\Parameter(name="parent_id", in="query", @OA\Schema(type="integer", nullable=true), description="Limit results to a specific parent folder ID (root if null)"),
     * @OA\Response(
     * response=200,
     * description="Folders retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Folders retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(ref="#/components/schemas/Folder")
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
    public function allFolders(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:name,created_at', // 'filename' is 'name', 'uploaded_date' is 'created_at'
            'order' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:folders,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = auth('api')->user();
        $query = Folder::where('user_id', $user->id);

        // Apply parent_id filter
        if ($request->has('parent_id') && $request->input('parent_id') !== null) {
            $query->where('parent_id', $request->input('parent_id'));
        } else {
            // If parent_id is not provided or is null, list root folders
            $query->whereNull('parent_id');
        }

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', '%' . $search . '%');
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at'); // Default to created_at for uploaded_date
        $order = $request->input('order', 'desc');

        // Map sort_by parameter to actual database columns
        $columnMap = [
            'filename' => 'name', // Assuming filename refers to folder name here
            'uploaded_date' => 'created_at', // Assuming uploaded_date refers to folder creation date
        ];
        $actualSortColumn = $columnMap[$sortBy] ?? 'created_at';

        $folders = $query->orderBy($actualSortColumn, $order)
                         ->paginate($request->input('limit', 20));

        return $this->sendResponse($folders->items(), 'Folders retrieved successfully', 200, [
            'pagination' => [
                'total_items' => $folders->total(),
                'total_pages' => $folders->lastPage(),
                'current_page' => $folders->currentPage(),
                'limit' => $folders->perPage(),
            ],
            'sort' => [
                'by' => $sortBy,
                'order' => $order,
            ]
        ]);
    }
    /**
     * @OA\Post(
     * path="/api/v1/folder/new",
     * summary="Create a new folder",
     * description="Creates a new folder for the authenticated user",
     * operationId="createFolder",
     * tags={"Folder Management"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"name"},
     * @OA\Property(property="parent_id", type="integer", nullable=true, example=1),
     * @OA\Property(property="name", type="string", example="New Project Folder"),
     * @OA\Property(property="privacy", type="string", enum={"public","private"}, example="private"),
     * @OA\Property(property="password", type="string", format="password", nullable=true, example="securepass123"),
     * @OA\Property(property="allow_download", type="boolean", example=true),
     * @OA\Property(property="watermark_images", type="boolean", example=false)
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Folder created successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Folder created successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/Folder")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
            'privacy' => 'in:public,private',
            'password' => 'nullable|string|min:6',
            'allow_download' => 'boolean',
            'watermark_images' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = auth('api')->user();
        $data = $request->only('parent_id', 'name', 'privacy', 'allow_download', 'watermark_images');
        $data['user_id'] = $user->id;

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $folder = Folder::create($data);

        return $this->sendResponse($folder, 'Folder created successfully', 201);
    }

    /**
     * @OA\Post(
     * path="/api/v1/folder/upload",
     * summary="Upload files into a folder",
     * description="Uploads one or more files to a specified folder",
     * operationId="uploadFilesInFolder",
     * tags={"Folder Management"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * required={"folder_id","files"},
     * @OA\Property(property="folder_id", type="integer", example=1, description="ID of the target folder"),
     * @OA\Property(property="files[]", type="array", @OA\Items(type="string", format="binary"), description="Array of files to upload")
     * )
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Files uploaded successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Files uploaded successfully"),
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/File"))
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Folder not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'folder_id' => 'required|exists:folders,id',
            'files' => 'required|array',
            'files.*' => 'file|max:102400', // Max 100MB per file
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = auth('api')->user();
        $folder = Folder::where('user_id', $user->id)->find($request->folder_id);

        if (!$folder) {
            return $this->sendError('Folder not found or not owned by user.', [], 404);
        }

        $uploadedFiles = [];
        foreach ($request->file('files') as $file) {
            $path = Storage::putFile("users/{$user->id}/folders/{$folder->id}", $file);
            
            $uploadedFile = File::create([
                'user_id' => $user->id,
                'folder_id' => $folder->id,
                'name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
            $uploadedFiles[] = $uploadedFile;
        }

        return $this->sendResponse($uploadedFiles, 'Files uploaded successfully');
    }

    /**
     * @OA\Get(
     * path="/api/v1/folder/{id}",
     * summary="Get folder details & list files/subfolders",
     * description="Retrieves details of a specific folder and its contents (files and subfolders)",
     * operationId="getFolderDetails",
     * tags={"Folder Management"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the folder", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Folder details retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Folder details retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="folder", ref="#/components/schemas/Folder"),
     * @OA\Property(property="files", type="array", @OA\Items(ref="#/components/schemas/File")),
     * @OA\Property(property="subfolders", type="array", @OA\Items(ref="#/components/schemas/Folder"))
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Folder not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $folder = Folder::where('user_id', $user->id)->find($id);

        if (!$folder) {
            return $this->sendError('Folder not found or not owned by user.', [], 404);
        }

        $files = $folder->files()->get();
        $subfolders = $folder->subfolders()->get();

        return $this->sendResponse([
            'folder' => $folder,
            'files' => $files,
            'subfolders' => $subfolders,
        ], 'Folder details retrieved successfully');
    }

    /**
     * @OA\Patch(
     * path="/api/v1/folder/{id}",
     * summary="Update folder info",
     * description="Updates properties of a specific folder",
     * operationId="updateFolder",
     * tags={"Folder Management"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the folder", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="name", type="string", nullable=true, example="Renamed Folder"),
     * @OA\Property(property="privacy", type="string", enum={"public","private"}, nullable=true, example="public"),
     * @OA\Property(property="password", type="string", format="password", nullable=true, example="newpass123"),
     * @OA\Property(property="allow_download", type="boolean", nullable=true, example=false),
     * @OA\Property(property="watermark_images", type="boolean", nullable=true, example=true)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Folder updated successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Folder updated successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/Folder")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Folder not found"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth('api')->user();
        $folder = Folder::where('user_id', $user->id)->find($id);

        if (!$folder) {
            return $this->sendError('Folder not found or not owned by user.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'privacy' => 'sometimes|in:public,private',
            'password' => 'nullable|string|min:6', // Can be null to remove password
            'allow_download' => 'sometimes|boolean',
            'watermark_images' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $data = $request->only('name', 'privacy', 'allow_download', 'watermark_images');
        if ($request->has('password')) {
            $data['password'] = $request->password ? Hash::make($request->password) : null;
        }

        $folder->update($data);

        return $this->sendResponse($folder, 'Folder updated successfully');
    }

    /**
     * @OA\Delete(
     * path="/api/v1/folder/{id}",
     * summary="Delete folder (move to trash)",
     * description="Moves a folder and its contents to the trash using soft delete",
     * operationId="deleteFolder",
     * tags={"Folder Management"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the folder", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Folder moved to trash successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Folder moved to trash successfully")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Folder not found")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $folder = Folder::where('user_id', $user->id)->find($id);

        if (!$folder) {
            return $this->sendError('Folder not found or not owned by user.', [], 404);
        }

        // Soft delete the folder and its associated files
        $folder->delete(); // This triggers soft delete

        // Optionally, you might want to soft delete files within this folder as well
        // Folder::withTrashed()->find($id)->files()->delete(); // Example if you want to ensure files are also soft-deleted

        return $this->sendResponse([], 'Folder moved to trash successfully');
    }
}