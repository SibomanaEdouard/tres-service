<?php

namespace App\Http\Controllers\Api;

use App\Models\File;
use App\Models\Folder;
use App\Models\ShareLink; 
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StatsController extends BaseController
{
    /**
     * @OA\Get(
     * path="/api/v1/folder/stats/{id}",
     * summary="Folder stats (views, downloads, etc.)",
     * description="Retrieves usage statistics for a specific folder",
     * operationId="getFolderStats",
     * tags={"Stats & Analytics"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the folder", @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Folder stats retrieved successfully"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Folder not found")
     * )
     */
    public function folderStats(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $folder = Folder::where('user_id', $user->id)->find($id);

        if (!$folder) {
            return $this->sendError('Folder not found or not owned by user.', [], 404);
        }

        $totalFiles = $folder->files()->count();
        $totalSubfolders = $folder->subfolders()->count();
        $totalDownloads = $folder->files()->sum('total_downloads');
        $totalSize = $folder->files()->sum('file_size'); // in bytes

        return $this->sendResponse([
            'folder_id' => $folder->id,
            'total_files' => $totalFiles,
            'total_subfolders' => $totalSubfolders,
            'total_downloads' => $totalDownloads,
            'total_size_bytes' => $totalSize,
            // Add more specific stats like views if you implement a view tracking mechanism
        ], 'Folder stats retrieved successfully');
    }

    /**
     * @OA\Get(
     * path="/api/v1/file/stats/{id}",
     * summary="File stats (with timeframe, countries, referrers, browsers, OS)",
     * description="Retrieves detailed usage statistics for a specific file, including a timeframe",
     * operationId="getFileStats",
     * tags={"Stats & Analytics"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID of the file", @OA\Schema(type="integer")),
     * @OA\Parameter(name="timeframe", in="query", description="Timeframe for stats (last 24h, 7d, 30d, 12mo)",
     * @OA\Schema(type="string", enum={"24h", "7d", "30d", "12mo"}, default="30d")),
     * @OA\Response(response=200, description="File stats retrieved successfully"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="File not found")
     * )
     */
    public function fileStats(Request $request, string $id): JsonResponse
    {
        $user = auth('api')->user();
        $file = File::where('user_id', $user->id)->find($id);

        if (!$file) {
            return $this->sendError('File not found or not owned by user.', [], 404);
        }

        $timeframe = $request->input('timeframe', '30d');
        $dateFrom = match ($timeframe) {
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            '12mo' => now()->subYear(),
            default => now()->subMonth(),
        };

        // These stats (countries, referrers, browsers, OS) require a logging system
        // for file access (e.g., a 'file_access_logs' table).
        // For now, this is a placeholder with random data.

        $stats = [
            'file_id' => $file->id,
            'total_downloads_all_time' => $file->total_downloads,
            'downloads_in_timeframe' => rand(10, 100),
            'views_in_timeframe' => rand(50, 200),
            'countries' => [
                ['country' => 'USA', 'count' => 50],
                ['country' => 'Germany', 'count' => 30],
            ],
            'top_referrers' => [
                ['referrer' => 'google.com', 'count' => 20],
                ['referrer' => 'facebook.com', 'count' => 15],
            ],
            'browsers' => [
                ['browser' => 'Chrome', 'count' => 70],
                ['browser' => 'Firefox', 'count' => 20],
            ],
            'os' => [
                ['os' => 'Windows', 'count' => 80],
                ['os' => 'macOS', 'count' => 10],
            ],
        ];

        return $this->sendResponse($stats, 'File stats retrieved successfully');
    }

    /**
     * @OA\Get(
     * path="/api/v1/used-storage",
     * summary="Get total used storage and other user-level file statistics",
     * description="Retrieves total storage space used by the authenticated user, along with file counts and shared file counts.",
     * operationId="getUsedStorageAndFileStats",
     * tags={"Stats & Analytics"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="Total used storage and file stats retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Total used storage and file stats retrieved successfully"),
     * @OA\Property(property="data", type="object",
     * @OA\Property(property="total_files_in_system", type="integer", example=500, description="Total files visible/accessible to the user (owned + shared)"),
     * @OA\Property(property="my_files_count", type="integer", example=300, description="Total files owned by the current user"),
     * @OA\Property(property="files_shared_with_me_count", type="integer", example=200, description="Total files shared with the current user"),
     * @OA\Property(property="used_storage_bytes", type="integer", example=123456789, description="Total storage used by my files in bytes"),
     * @OA\Property(property="total_storage_quota_bytes", type="integer", example=5368709120, description="Total available storage quota in bytes (e.g., 5GB)"),
     * @OA\Property(property="remaining_storage_bytes", type="integer", example=4000000000, description="Remaining storage quota in bytes")
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function usedStorage(): JsonResponse
    {
        $user = auth('api')->user();

        // My Files (owned by the user)
        $myFiles = File::where('user_id', $user->id)->get();
        $myFilesCount = $myFiles->count();
        $usedStorageBytes = $myFiles->sum('file_size');

        // Files Shared With Me
        $sharedFilesWithMeLinks = ShareLink::where('recipient_email', $user->email)
                                           ->where('shareable_type', File::class)
                                           ->where(function($query) {
                                               $query->whereNull('expires_at')
                                                     ->orWhere('expires_at', '>', now());
                                           })
                                           ->get();
        $filesSharedWithMeCount = $sharedFilesWithMeLinks->unique('shareable_id')->count();

        // Total files accessible to the user (owned + shared)
        // This is a simplified sum. A more accurate "total files in system" might deduplicate
        // or account for public links that don't specify recipient_email.
        $totalFilesInSystem = $myFilesCount + $filesSharedWithMeCount;

        // Assuming a predefined total storage quota (e.g., 5GB for this example)
        $totalStorageQuotaBytes = 5 * 1024 * 1024 * 1024; // 5 GB in bytes
        $remainingStorageBytes = $totalStorageQuotaBytes - $usedStorageBytes;
        if ($remainingStorageBytes < 0) {
            $remainingStorageBytes = 0; // Cannot be negative
        }

        return $this->sendResponse([
            'total_files_in_system' => $totalFilesInSystem,
            'my_files_count' => $myFilesCount,
            'files_shared_with_me_count' => $filesSharedWithMeCount,
            'used_storage_bytes' => $usedStorageBytes,
            'total_storage_quota_bytes' => $totalStorageQuotaBytes,
            'remaining_storage_bytes' => $remainingStorageBytes,
        ], 'Total used storage and file stats retrieved successfully');
    }
}