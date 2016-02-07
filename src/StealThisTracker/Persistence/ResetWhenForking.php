<?php

/**
 * When a persistence object is used in a forked process and implements this
 * interface, resetAfterForking will be called after the fork to, for example,
 * re-establish database connections.
 *
 * @package StealThisTracker
 * @subpackage Persistence
 */
interface StealThisTracker_Persistence_ResetWhenForking
{
    /**
     * To be called after the child-process is forked.
     */
    public function resetAfterForking();
}