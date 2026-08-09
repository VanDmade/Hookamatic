<?php

use Illuminate\Support\Facades\Route;
use VanDmade\Hookamatic\Http\Controllers\EventTypeController;
use VanDmade\Hookamatic\Http\Controllers\SubscriberController;

Route::middleware('can:manage-hookamatic')
    ->prefix('hookamatic')
    ->name('hookamatic.')
    ->group(function() {
        Route::get('list/subscribers', [SubscriberController::class, 'list'])->name('subscribers.list');
        Route::prefix('subscriber')
            ->name('subscribers.')
            ->group(function() {
                Route::get('data', [SubscriberController::class, 'data'])->name('data');
                Route::post('/', [SubscriberController::class, 'store'])->name('store');
                Route::get('{subscriber}', [SubscriberController::class, 'get'])->name('show');
                Route::put('{subscriber}', [SubscriberController::class, 'update'])->name('update');
                Route::delete('{subscriber}', [SubscriberController::class, 'destroy'])->name('destroy');
            });
        Route::get('list/event-types', [EventTypeController::class, 'list'])->name('event-types.list');
        Route::prefix('event-type')
            ->name('event-types.')
            ->group(function() {
                Route::get('data', [EventTypeController::class, 'data'])->name('data');
                Route::post('/', [EventTypeController::class, 'store'])->name('store');
                Route::get('{eventType}', [EventTypeController::class, 'get'])->name('show');
                Route::put('{eventType}', [EventTypeController::class, 'update'])->name('update');
                Route::delete('{eventType}', [EventTypeController::class, 'destroy'])->name('destroy');
            });
    });
