<?php

declare(strict_types=1);

namespace App\DTO;

class DropEfficiencyResult
{
    public function __construct(
        public readonly float $cycleChance,
        public readonly float $missionsUsed,
        public readonly float $efficiency,
    ) {
    }

    public function getCycleChanceFormatted(): string
    {
        return (string) $this->cycleChance;
    }

    public function getEfficiencyFormatted(): string
    {
        return number_format($this->efficiency, 2).'%';
    }
}
