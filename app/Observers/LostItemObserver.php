<?php

namespace App\Observers;

use App\Models\LostItem;
use App\Services\AutoMatchingService;

class LostItemObserver
{
    protected $autoMatchingService;

    public function __construct(AutoMatchingService $autoMatchingService)
    {
        $this->autoMatchingService = $autoMatchingService;
    }

    /**
     * Handle the LostItem "created" event.
     */
    public function created(LostItem $lostItem): void
    {
        $this->autoMatchingService->processNewItem($lostItem, 'lost');
    }

    /**
     * Handle the LostItem "updated" event.
     */
    public function updated(LostItem $lostItem): void
    {
        //
    }

    /**
     * Handle the LostItem "deleted" event.
     */
    public function deleted(LostItem $lostItem): void
    {
        //
    }

    /**
     * Handle the LostItem "restored" event.
     */
    public function restored(LostItem $lostItem): void
    {
        //
    }

    /**
     * Handle the LostItem "force deleted" event.
     */
    public function forceDeleted(LostItem $lostItem): void
    {
        //
    }
}
