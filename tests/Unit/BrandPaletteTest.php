<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BrandPalette;
use PHPUnit\Framework\TestCase;

class BrandPaletteTest extends TestCase
{
    public function test_it_selects_contrasting_foregrounds_for_light_and_dark_colors(): void
    {
        $this->assertSame('#FFFFFF', BrandPalette::contrastingForeground('#0F172A'));
        $this->assertSame('#0F172A', BrandPalette::contrastingForeground('#FDE047'));
    }

    public function test_it_derives_accessible_ink_for_light_brand_colors(): void
    {
        $this->assertNotSame('#FDE047', BrandPalette::accessibleInk('#FDE047'));
        $this->assertSame('#1D4ED8', BrandPalette::accessibleInk('#1D4ED8'));
    }
}
