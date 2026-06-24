<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;

final class BrandPalette
{
    private const DARK_INK = '#0F172A';

    private const LIGHT_INK = '#FFFFFF';

    /** @return array<string, string> */
    public static function forOrganization(?Organization $organization): array
    {
        $primary = self::normalize($organization?->primary_color, '#2563EB');
        $secondary = self::normalize($organization?->secondary_color, '#0F172A');
        $accent = self::normalize($organization?->accent_color, '#14B8A6');

        return [
            '--color-brand-primary' => $primary,
            '--color-brand-secondary' => $secondary,
            '--color-brand-accent' => $accent,
            '--color-brand-energy' => $accent,
            '--color-brand-on-primary' => self::contrastingForeground($primary),
            '--color-brand-on-secondary' => self::contrastingForeground($secondary),
            '--color-brand-on-accent' => self::contrastingForeground($accent),
            '--color-brand-primary-ink' => self::accessibleInk($primary),
            '--color-brand-accent-ink' => self::accessibleInk($accent),
        ];
    }

    public static function cssVariables(?Organization $organization): string
    {
        return collect(self::forOrganization($organization))
            ->map(fn (string $value, string $name): string => "{$name}: {$value}")
            ->implode('; ');
    }

    public static function contrastingForeground(string $background): string
    {
        $background = self::normalize($background, '#000000');
        $lightContrast = self::contrastRatio($background, self::LIGHT_INK);
        $darkContrast = self::contrastRatio($background, self::DARK_INK);

        return $lightContrast >= $darkContrast ? self::LIGHT_INK : self::DARK_INK;
    }

    public static function accessibleInk(string $color, string $background = '#FFFFFF'): string
    {
        $color = self::normalize($color, self::DARK_INK);
        $background = self::normalize($background, self::LIGHT_INK);
        if (self::contrastRatio($color, $background) >= 4.5) {
            return $color;
        }

        $target = self::contrastRatio(self::DARK_INK, $background)
            >= self::contrastRatio(self::LIGHT_INK, $background)
            ? self::DARK_INK
            : self::LIGHT_INK;

        for ($weight = 10; $weight <= 100; $weight += 10) {
            $candidate = self::mix($color, $target, $weight / 100);
            if (self::contrastRatio($candidate, $background) >= 4.5) {
                return $candidate;
            }
        }

        return $target;
    }

    private static function contrastRatio(string $first, string $second): float
    {
        $firstLuminance = self::relativeLuminance($first);
        $secondLuminance = self::relativeLuminance($second);
        $lighter = max($firstLuminance, $secondLuminance);
        $darker = min($firstLuminance, $secondLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function relativeLuminance(string $hex): float
    {
        $channels = array_map(
            static function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim(self::normalize($hex, '#000000'), '#'), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function mix(string $source, string $target, float $weight): string
    {
        $sourceChannels = str_split(ltrim($source, '#'), 2);
        $targetChannels = str_split(ltrim($target, '#'), 2);
        $mixed = '';

        foreach ([0, 1, 2] as $index) {
            $value = (int) round(
                hexdec($sourceChannels[$index]) * (1 - $weight)
                + hexdec($targetChannels[$index]) * $weight,
            );
            $mixed .= str_pad(strtoupper(dechex($value)), 2, '0', STR_PAD_LEFT);
        }

        return '#'.$mixed;
    }

    private static function normalize(?string $color, string $fallback): string
    {
        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)
            ? strtoupper($color)
            : $fallback;
    }
}
