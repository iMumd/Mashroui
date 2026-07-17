<?php

namespace App\Support;

class CurrentTerm
{
    private ?int $termId = null;

    public function set(?int $termId): void
    {
        $this->termId = $termId;
    }

    public function get(): ?int
    {
        return $this->termId;
    }
}
