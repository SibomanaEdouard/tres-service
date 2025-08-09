<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use App\Models\File;
use App\Models\Folder;

class PublicShareController extends Controller
{
    /**
     * Handles public share links for files and folders.
     * Checks link validity, password, and serves content.
     *
     * @OA\Get(
     * path="/share/{link}",
     * summary="Access public share link",
     * description="Accesses content shared via a public link. Requires password if protected.",
     * operationId="accessPublicShareLink",
     * tags={"Sharing & Links"},
     * @OA\Parameter(name="link", in="path", required=true, description="Unique share link string", @OA\Schema(type="string")),
     * @OA\RequestBody(
     * @OA\JsonContent(
     * @OA\Property(property="password", type="string", format="password", nullable=true, example="linkpass")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Content retrieved successfully",
     * @OA\MediaType(mediaType="application/octet-stream")
     * ),
     * @OA\Response(response=401, description="Unauthorized: Invalid password or link expired"),
     * @OA\Response(response=404, description="Link not found")
     * )
     */
    public function show(Request $request, string $link)
    {
        $shareLink = ShareLink::where('link', url('/share/' . $link))->first();

        if (!$shareLink) {
            return response()->json(['message' => 'Share link not found.'], 404);
        }

        // Check if link has expired
        if ($shareLink->expires_at && $shareLink->expires_at->isPast()) {
            return response()->json(['message' => 'Share link has expired.'], 401);
        }

        // Check password if protected
        if ($shareLink->password && !Hash::check($request->input('password'), $shareLink->password)) {
            return response()->json(['message' => 'Incorrect password.'], 401);
        }

        $shareable = $shareLink->shareable; // Get the associated File or Folder model

        if (!$shareable) {
            return response()->json(['message' => 'Shared content not found.'], 404);
        }

        if ($shareable instanceof File) {
            // Serve the file
            if (!Storage::exists($shareable->file_path)) {
                return response()->json(['message' => 'File not found on storage.'], 404);
            }
            // Increment download count and update last access date (if permissions allow download)
            if ($shareLink->permissions === 'upload-download-view' || $shareLink->permissions === 'view') {
                 $shareable->increment('total_downloads');
                 $shareable->update(['last_access_date' => now()]);
            }
            return Storage::download($shareable->file_path, $shareable->name);

        } elseif ($shareable instanceof Folder) {
            // List folder contents
            $files = $shareable->files()->get();
            $subfolders = $shareable->subfolders()->get();

            return response()->json([
                'folder' => $shareable,
                'files' => $files,
                'subfolders' => $subfolders,
                'message' => 'Folder contents retrieved successfully.'
            ]);
        }

        return response()->json(['message' => 'Invalid shareable type.'], 500);
    }
}