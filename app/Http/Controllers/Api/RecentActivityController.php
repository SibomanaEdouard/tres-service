<?php

namespace App\Http\Controllers\Api;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RecentActivityController extends BaseController
{
    /**
     * @OA\Get(
     * path="/api/v1/recent/files",
     * summary="Recently uploaded files",
     * description="Retrieves a list of recently uploaded files for the authenticated user",
     * operationId="getRecentFiles",
     * tags={"Recent Activity"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     * @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=20)),
     * @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", default="uploaded_date", enum={"filename", "uploaded_date", "total_downloads", "filesize", "last_access_date"})),
     * @OA\Parameter(name="order", in="query", @OA\Schema(type="string", default="desc", enum={"asc", "desc"})),
     * @OA\Response(response=200, description="Recent files retrieved successfully"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function recentFiles(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $limit = $request->input('limit', 20);
        $sortBy = $request->input('sort_by', 'created_at'); // Using created_at as uploaded_date
        $order = $request->input('order', 'desc');

        $files = File::where('user_id', $user->id)
                     ->orderBy($sortBy, $order)
                     ->paginate($limit);

        return $this->sendResponse($files->items(), 'Recent files retrieved successfully', 200, [
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
     * path="/api/v1/recent",
     * summary="Recent activity (files & folders)",
     * description="Retrieves a combined list of recent activity including files and folders for the authenticated user",
     * operationId="getRecentActivity",
     * tags={"Recent Activity"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     * @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=20)),
     * @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", default="uploaded_date", enum={"filename", "uploaded_date", "total_downloads", "filesize", "last_access_date"})),
     * @OA\Parameter(name="order", in="query", @OA\Schema(type="string", default="desc", enum={"asc", "desc"})),
     * @OA\Response(response=200, description="Recent activity retrieved successfully"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $limit = $request->input('limit', 20);
        $order = $request->input('order', 'desc');

        $recentFiles = File::where('user_id', $user->id)
                           ->orderBy('updated_at', $order) // Using 'updated_at' for general recency
                           ->limit($limit / 2) // Distribute limit between files and folders
                           ->get();

        $recentFolders = Folder::where('user_id', $user->id)
                               ->orderBy('updated_at', $order)
                               ->limit($limit / 2) // Distribute limit between files and folders
                               ->get();

        $combined = collect()->concat($recentFiles)->concat($recentFolders);

        // Sort the combined collection in memory for true recency across types
        $combined = $combined->sortByDesc('updated_at')->values();

        return $this->sendResponse([
            'files' => $recentFiles,
            'folders' => $recentFolders,
            'all_recent_items' => $combined // Uncomment if you want a single merged list
        ], 'Recent activity retrieved successfully');
    }
}