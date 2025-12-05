<?php

declare(strict_types=1);

namespace App\DTO;

readonly class MissionDrop
{
    public function __construct(
        public string $planet,
        public string $mission,
        public string $rotation,
        public float $chance,
        public string $gameMode,
    ) {
    }
}
