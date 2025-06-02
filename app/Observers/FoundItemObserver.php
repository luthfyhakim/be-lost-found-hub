<?php

namespace App\Observers;

use App\Models\FoundItem;
use App\Services\AutoMatchingService;

class FoundItemObserver
{
    protected $autoMatchingService;

    public function __construct(AutoMatchingService $autoMatchingService)
    {
        $this->autoMatchingService = $autoMatchingService;
    }

    /**
     * Handle the FoundItem "created" event.
     */
    public function created(FoundItem $foundItem): void
    {
        $this->autoMatchingService->processNewItem($foundItem, 'found');
    }

    /**
     * Handle the FoundItem "updated" event.
     */
    public function updated(FoundItem $foundItem): void
    {
        //
    }

    /**
     * Handle the FoundItem "deleted" event.
     */
    public function deleted(FoundItem $foundItem): void
    {
        //
    }

    /**
     * Handle the FoundItem "restored" event.
     */
    public function restored(FoundItem $foundItem): void
    {
        //
    }

    /**
     * Handle the FoundItem "force deleted" event.
     */
    public function forceDeleted(FoundItem $foundItem): void
    {
        //
    }
}
