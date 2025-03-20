<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\XapiController;
use App\Http\Controllers\ContentController;
use Illuminate\Support\Facades\Route;

// Kimlik doğrulama rotaları
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Yönetici rotaları
Route::middleware('admin')->prefix('admin')->group(function () {
    // Ana yönetici sayfası (Kullanıcı Yönetimi)
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    
    // Kullanıcı yönetimi rotaları
    Route::get('/users/create', [AdminController::class, 'showUserForm'])->name('admin.users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::get('/users/{id}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
    Route::put('/users/{id}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
    Route::get('/users', [AdminController::class, 'listUsers'])->name('admin.users.index'); // Bu satırı ekleyin
    
    // İçerik yönetimi rotaları
    Route::get('/content', [ContentController::class, 'index'])->name('content.index');
    Route::post('/content/upload', [ContentController::class, 'upload'])->name('content.upload');
    Route::get('/content/{id}', [ContentController::class, 'show'])->name('content.show');
    Route::get('/content/{id}/launch', [ContentController::class, 'launch'])->name('content.launch'); // Bu rotayı ekleyin
    
    // xAPI raporlama rotaları
    Route::get('/reports', [XapiController::class, 'dashboard'])->name('dashboard.reports');
    Route::get('/activity/{id}', [XapiController::class, 'activityDetails'])->name('activity.details');
    Route::get('/activity/{activityId}/user/{userId}', [XapiController::class, 'userActivityDetails'])->name('user.activity.details');
});

// Kullanıcı rotaları
Route::middleware('user')->prefix('user')->group(function () {
    Route::get('/', [UserController::class, 'dashboard'])->name('user.dashboard');
    Route::get('/content/{contentPath}/launch', [UserController::class, 'launchContent'])->name('user.content.launch');
});

// İçerik dosyalarına erişim için genel route
Route::get('content-serve/{path}', function ($path) {
    $fullPath = storage_path('app/public/xapi-content/' . $path);
    
    if (file_exists($fullPath)) {
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $mimeType = [
            'html' => 'text/html',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'json' => 'application/json',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
        ][$extension] ?? 'application/octet-stream';
        
        return response()->file($fullPath, ['Content-Type' => $mimeType]);
    }
    
    abort(404);
})->where('path', '.*');

// Ana sayfa yönlendirmesi
Route::get('/', function () {
    if (auth()->check()) {
        if (auth()->user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('user.dashboard');
    }
    return redirect()->route('login');
});
