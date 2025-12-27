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
        return (string) round($this->cycleChance * 100, 2) . '%';
    }

    public function getEfficiencyFormatted(): string
    {
        return (string) round($this->efficiency * 100, 2) . '%';
    }
}
