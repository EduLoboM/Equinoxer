<?php

namespace App\DTO;

readonly class RelicDrop
{
    public function __construct(
        public string $item,
        public string $rarity,
        public float $chance,
    ) {
    }
}
