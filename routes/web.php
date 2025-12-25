<?php

use App\Livewire\Admin\BoundaryImport;
use App\Livewire\Admin\CleanupManager;
use App\Livewire\Admin\DataVersionTable;
use App\Livewire\Admin\ImportManager;
use App\Livewire\Admin\ImportProgress;
use App\Livewire\Auth\Login;
use App\Livewire\Tools\BoundaryViewer;
use App\Livewire\Tools\PostcodeLookup;
use App\Livewire\Tools\PropertyMap;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Login route
Route::get('/login', Login::class)->name('login');

// Logout route
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');

    // Admin routes
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/import', ImportManager::class)->name('import');
        Route::get('/import/progress/{import}', ImportProgress::class)->name('import.progress');
        Route::get('/boundaries', BoundaryImport::class)->name('boundaries');
        Route::get('/versions', DataVersionTable::class)->name('versions');
        Route::get('/cleanup', CleanupManager::class)->name('cleanup');
    });

    // Tools routes
    Route::prefix('tools')->name('tools.')->group(function () {
        Route::get('/lookup', PostcodeLookup::class)->name('lookup');
        Route::get('/map', PropertyMap::class)->name('map');
        Route::get('/boundaries', BoundaryViewer::class)->name('boundaries');
    });
});
