<?php

namespace App\Service;

use App\DTO\DropEfficiencyResult;

class DropEfficiencyCalculator
{
    private const ROTATION_POSITIONS = ['A' => 1, 'B' => 2, 'C' => 3];

    public function calculate(array $chances, string $maxRotation): DropEfficiencyResult
    {
        $probabilities = array_map(
            fn (float $chance) => $chance / 100,
            $chances
        );

        $productOfComplements = array_reduce(
            $probabilities,
            fn (float $carry, float $p) => $carry * (1 - $p),
            1.0
        );

        $cycleChance = 1 - $productOfComplements;

        $missions = (self::ROTATION_POSITIONS[$maxRotation] ?? 3) + 1;
        $efficiency = $cycleChance / $missions;

        return new DropEfficiencyResult($cycleChance, $missions, $efficiency);
    }

    public function calculateFromChanceStrings(array $chanceStrings, string $maxRotation): DropEfficiencyResult
    {
        $chances = array_map(
            fn (string $c) => floatval(rtrim($c, '%')),
            $chanceStrings
        );

        return $this->calculate($chances, $maxRotation);
    }
}
