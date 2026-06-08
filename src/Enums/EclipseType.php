<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum EclipseType: string
{
    case TOTAL = 'total';
    case ANNULAR = 'annular';
    case PARTIAL = 'partial';
    case HYBRID = 'hybrid';
    case PENUMBRAL = 'penumbral';

    /**
     * Map a swetest first-token type word onto the enum.
     * swetest emits `anntot` for hybrid (annular-total) eclipses.
     */
    public static function fromToken(string $token): ?self
    {
        return match (strtolower(trim($token))) {
            'total' => self::TOTAL,
            'annular' => self::ANNULAR,
            'partial' => self::PARTIAL,
            'penumbral' => self::PENUMBRAL,
            'anntot', 'hybrid' => self::HYBRID,
            default => null,
        };
    }

    public static function isTypeToken(string $token): bool
    {
        return self::fromToken($token) !== null;
    }
}
