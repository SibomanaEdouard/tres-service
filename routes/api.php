<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\TrashController;
use App\Http\Controllers\Api\ShareLinkController;
use App\Http\Controllers\Api\RecentActivityController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\AccountSettingsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\MoveCopyController;
use App\Http\Controllers\PublicShareController; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// This is an example route. You can keep it or remove it.
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// --- Authentication Routes ---
Route::prefix('v1/auth')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('logout', 'logout')->middleware('auth:api');
    Route::post('refresh', 'refresh')->middleware('auth:api');
    Route::get('me', 'me')->middleware('auth:api');
    Route::put('update-account', 'updateAccount')->middleware('auth:api');
    Route::delete('delete-account', 'deleteAccount')->middleware('auth:api');
});

// All other API V1 Routes - Protected by 'auth:api' middleware
Route::prefix('v1')->middleware('auth:api')->group(function () {

    // Folder Management
    Route::prefix('folder')->controller(FolderController::class)->group(function () {
        Route::post('new', 'store'); // Create new folder
        Route::post('upload', 'upload'); // Upload files into a folder
        Route::get('{id}', 'show'); // Get folder details & list files/subfolders
        Route::patch('{id}', 'update'); // Update folder info
        Route::delete('{id}', 'destroy'); // Delete folder (move to trash)
    });

    // File Management
    Route::prefix('file')->controller(FileController::class)->group(function () {
        Route::get('{id}', 'show'); // Get file details
        Route::patch('{id}', 'update'); // Update file metadata
        Route::delete('{id}', 'destroy'); // Delete a single file (move to trash)
        Route::delete('/', 'deleteMultiple'); // Delete multiple files (move to trash)
        Route::get('download/{id}', 'download'); // Download a single file
        Route::post('download', 'downloadMultiple'); // Download multiple files as ZIP
        Route::get('all', 'allFiles'); // List all files with pagination, sorting, search
    });

    // Trash & Restore Endpoints
    Route::prefix('trash')->controller(TrashController::class)->group(function () {
        Route::get('/', 'index'); // List all trashed files & folders
        Route::post('restore', 'restore'); // Restore a file or folder from trash
        Route::delete('file/{id}', 'deleteFilePermanently'); // Permanently delete single file from trash
        Route::delete('file/all', 'emptyTrash'); // Empty trash (all files/folders)
    });

    // Recent Activity Endpoints
    Route::prefix('recent')->controller(RecentActivityController::class)->group(function () {
        Route::get('files', 'recentFiles'); // Recently uploaded files
        Route::get('/', 'recentActivity'); // Recent activity (files & folders)
    });

    // Stats & Analytics Endpoints
    Route::prefix('stats')->controller(StatsController::class)->group(function () {
        Route::get('folder/{id}', 'folderStats'); // Folder stats (views, downloads, etc.)
        Route::get('file/{id}', 'fileStats'); // File stats (with timeframe, countries, etc.)
        // Route::get('used-storage', 'usedStorage'); // Get total used storage
    });
       // Moved used-storage here to be directly under v1
    Route::get('used-storage', [StatsController::class, 'usedStorage']);

    // Sharing & Links Endpoints
    Route::prefix('link')->controller(ShareLinkController::class)->group(function () {
        Route::post('file/{id}', 'createFileLink'); // Create share link for a file
        Route::post('folder/{id}', 'createFolderLink'); // Create share link for a folder
        Route::get('file/{id}', 'getFileLinkDetails'); // Get file link details
        Route::get('folder/{id}', 'getFolderLinkDetails'); // Get folder link details
        Route::patch('file/{id}', 'updateFileShareLink'); // Update file share link settings
        Route::patch('folder/{id}', 'updateFolderShareLink'); // Update folder share link settings
        Route::delete('file/{id}', 'deleteFileShareLink'); // Delete file share link
        Route::delete('folder/{id}', 'deleteFolderShareLink'); // Delete folder share link
    });
    Route::get('shared-with-me', [ShareLinkController::class, 'getSharedWithMe']); // Get items shared with current user

    // File Listing & Pagination (moved all-files to FileController, will need implementation)
    // Removed duplicate all-files as it is covered under file listing.
    Route::get('all-folders', [FolderController::class, 'allFolders']); // List all folders with pagination, sorting, search
        // Global File/Folder Listing with Pagination, Sorting, Search
    Route::get('all-files', [FileController::class, 'allFiles']); // List all files
    // Route::get('all-folders', [FolderController::class, 'allFolders']); // List all folders

    // Search
    Route::get('search', [SearchController::class, 'search']); // Search files/folders by name/description

    // Move file/folder
    Route::post('move', [MoveCopyController::class, 'move']); // Move file(s) or folder(s) to another folder

    // Copy file/folder
    Route::post('copy', [MoveCopyController::class, 'copy']); // Duplicate file(s) or folder(s)

    // Account Settings Endpoints
    Route::prefix('account/settings')->controller(AccountSettingsController::class)->group(function () {
        Route::get('/', 'index'); // Get current account settings
        Route::patch('/', 'update'); // Update account settings
    });
});

// Public Share Link Access (no authentication required)
// This route will handle requests for publicly shared files/folders
Route::get('share/{link}', [PublicShareController::class, 'show'])->name('share.show');
