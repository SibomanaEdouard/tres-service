<?php

namespace App\Http\Controllers\Api;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SearchController extends BaseController
{
    /**
     * @OA\Get(
     * path="/api/v1/search",
     * summary="Search files/folders by name/description",
     * description="Searches for files or folders based on a keyword and type (file or folder)",
     * operationId="searchFilesFolders",
     * tags={"Search"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="query", in="query", required=true, description="Search keyword", @OA\Schema(type="string")),
     * @OA\Parameter(name="type", in="query", description="Type of item to search (file|folder)", @OA\Schema(type="string", enum={"file","folder"}, nullable=true)),
     * @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     * @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=20)),
     * @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", default="created_at", enum={"name", "created_at"})),
     * @OA\Parameter(name="order", in="query", @OA\Schema(type="string", default="desc", enum={"asc", "desc"})),
     * @OA\Response(response=200, description="Search results retrieved successfully"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string',
            'type' => 'nullable|in:file,folder',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:name,created_at', // Example sortable fields
            'order' => 'nullable|string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = auth('api')->user();
        $query = $request->input('query');
        $type = $request->input('type');
        $limit = $request->input('limit', 20);
        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');

        $results = [
            'files' => [],
            'folders' => [],
        ];

        if ($type === 'file' || is_null($type)) {
            $files = File::where('user_id', $user->id)
                         ->where(function($q) use ($query) {
                             $q->where('name', 'like', '%' . $query . '%')
                               ->orWhere('description', 'like', '%' . $query . '%');
                         })
                         ->orderBy($sortBy === 'name' ? 'name' : 'created_at', $order)
                         ->paginate($limit);
            $results['files'] = $files->items();
            // You might want to return pagination meta for each type or combine.
        }

        if ($type === 'folder' || is_null($type)) {
            $folders = Folder::where('user_id', $user->id)
                             ->where('name', 'like', '%' . $query . '%')
                             ->orderBy($sortBy === 'name' ? 'name' : 'created_at', $order)
                             ->paginate($limit);
            $results['folders'] = $folders->items();
        }

        return $this->sendResponse($results, 'Search results retrieved successfully');
    }
}