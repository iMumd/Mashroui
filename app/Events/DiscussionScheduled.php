<?php

namespace App\Events;

use App\Models\Discussion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscussionScheduled
{
    use Dispatchable, SerializesModels;

    public function __construct(public Discussion $discussion) {}
}
