<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkspaceController;
use App\Livewire\People;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', People::class)->name('people');
    Route::get('/workers/new', [WorkspaceController::class, 'create'])->name('workers.create');
    Route::post('/workers', [WorkspaceController::class, 'store'])->name('workers.store')->block(10, 10);
    Route::get('/workers/{id}', [WorkspaceController::class, 'show'])->whereNumber('id')->name('workers.show');
    Route::post('/workers/{id}/transition', [WorkflowController::class, 'transition'])->whereNumber('id')->name('workers.transition')->block(10, 10);
    Route::post('/workers/{id}/tasks/{task}/complete', [WorkflowController::class, 'complete'])->whereNumber(['id', 'task'])->name('workers.complete')->block(10, 10);
    Route::post('/workers/{id}/equipment', [WorkflowController::class, 'assign'])->whereNumber('id')->name('workers.assign')->block(10, 10);
    Route::post('/workers/{id}/equipment/{asset}/return', [WorkflowController::class, 'returnEquipment'])->whereNumber(['id', 'asset'])->name('workers.return')->block(10, 10);
    Route::get('/equipment', [WorkspaceController::class, 'equipment'])->name('equipment');
    Route::get('/activity', [WorkspaceController::class, 'activity'])->name('activity');
});
