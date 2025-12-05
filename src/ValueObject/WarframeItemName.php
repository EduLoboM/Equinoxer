<?php

declare(strict_types=1);

namespace App\ValueObject;

readonly class WarframeItemName
{
    private const REFINEMENTS = ['Intact', 'Exceptional', 'Flawless', 'Radiant'];
    private const TIERS = ['Lith', 'Meso', 'Neo', 'Axi', 'Requiem'];

    public function __construct(
        private string $rawName,
    ) {
    }

    public function getCleanName(): string
    {
        $pattern = '/\s+\(?('.implode('|', self::REFINEMENTS).')\)?$/i';

        return trim(preg_replace($pattern, '', $this->rawName));
    }

    public function getTier(): ?string
    {
        foreach (self::TIERS as $tier) {
            if (false !== stripos($this->rawName, $tier)) {
                return $tier;
            }
        }

        return null;
    }

    public function isRelic(): bool
    {
        return false !== stripos($this->rawName, 'Relic')
            || null !== $this->getTier();
    }

    public function getSlug(): string
    {
        $clean = $this->getCleanName();
        $parts = explode(' ', $clean);

        if (count($parts) >= 2) {
            $tier = strtolower($parts[0]);
            $code = strtolower($parts[1]);

            if (in_array(ucfirst($tier), self::TIERS, true)) {
                return "{$tier}_{$code}";
            }
        }

        return strtolower(str_replace(' ', '_', $clean));
    }

    public function getRawName(): string
    {
        return $this->rawName;
    }
}
