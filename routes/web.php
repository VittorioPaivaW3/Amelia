<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GutRequestAttachmentController;
use App\Http\Controllers\GutRequestController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('./auth/login');
});

Route::get('/dashboard', [GutRequestController::class, 'dashboard'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/chamados', [GutRequestController::class, 'calls'])
    ->middleware(['auth', 'verified'])
    ->name('calls.index');
Route::get('/relatorios/export', [GutRequestController::class, 'export'])
    ->middleware(['auth', 'verified'])
    ->name('reports.export');

Route::middleware('auth')->group(function () {
    Route::get('/chat', function () {
        return view('chat');
    })->name('chat');
    Route::post('/chat', ChatController::class)->name('chat.respond');
    Route::get('/attachments/{attachment}', [GutRequestAttachmentController::class, 'download'])
        ->name('attachments.download');
    Route::get('/portal', function () {
        $user = request()->user();
        return match ($user?->role) {
            'mkt' => redirect()->route('portal.sector', ['sector' => 'mkt']),
            'juridico' => redirect()->route('portal.sector', ['sector' => 'juridico']),
            'rh' => redirect()->route('portal.sector', ['sector' => 'rh']),
            default => redirect()->route('dashboard'),
        };
    })->middleware('role:mkt,juridico,rh')->name('portal');
    Route::get('/portal/{sector}', [GutRequestController::class, 'sector'])
        ->middleware('role:mkt,juridico,rh')
        ->where('sector', 'mkt|juridico|rh')
        ->name('portal.sector');
    Route::patch('/gut-requests/{gutRequest}', [GutRequestController::class, 'update'])
        ->middleware('role:admin,mkt,juridico,rh')
        ->name('gut-requests.update');
    Route::middleware('role:admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
            Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [AdminUserController::class, 'updateRole'])->name('users.update-role');
        });
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
