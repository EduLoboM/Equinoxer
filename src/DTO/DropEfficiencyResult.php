<?php

namespace App\DTO;

readonly class DropEfficiencyResult
{
    public function __construct(
        public float $cycleChance,
        public int $missionsUsed,
        public float $efficiency,
    ) {
    }

    public function getCycleChanceFormatted(): string
    {
        return round($this->cycleChance * 100, 2).'%';
    }

    public function getEfficiencyFormatted(): string
    {
        return round($this->efficiency * 100, 2).'%';
    }
}
