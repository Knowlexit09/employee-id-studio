<?php

namespace Tests\Unit;

use App\Services\TemplateLayout;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TemplateLayoutTest extends TestCase
{
    public function test_default_layout_is_valid_for_standard_cr80(): void
    {
        $layout = TemplateLayout::initial();
        $this->assertEquals($layout, TemplateLayout::normalize($layout, 85.60, 53.98));
    }

    public function test_default_layout_is_scaled_to_custom_card_dimensions(): void
    {
        foreach ([[40.0, 30.0], [85.60, 53.98], [220.0, 310.0], [54.0, 85.60]] as [$width, $height]) {
            $layout = TemplateLayout::initial($width, $height);
            $normalized = TemplateLayout::normalize($layout, $width, $height);
            $this->assertEquals($layout, $normalized);
            foreach (['front', 'back'] as $side) {
                foreach ($layout[$side]['elements'] as $element) {
                    $this->assertGreaterThanOrEqual(0, $element['x']);
                    $this->assertGreaterThanOrEqual(0, $element['y']);
                    $this->assertLessThanOrEqual($width + 0.01, $element['x'] + $element['w']);
                    $this->assertLessThanOrEqual($height + 0.01, $element['y'] + $element['h']);
                }
            }
        }
    }

    public function test_sensitive_government_number_cannot_be_bound_to_card(): void
    {
        $layout = TemplateLayout::initial();
        $layout['front']['elements'][0]['binding'] = 'sss_number';
        $this->expectException(ValidationException::class);
        TemplateLayout::normalize($layout, 85.60, 53.98);
    }

    public function test_duplicate_element_ids_are_rejected(): void
    {
        $layout = TemplateLayout::initial();
        $layout['front']['elements'][1]['id'] = $layout['front']['elements'][0]['id'];
        $this->expectException(ValidationException::class);
        TemplateLayout::normalize($layout, 85.60, 53.98);
    }

    public function test_overflowing_element_is_rejected(): void
    {
        $layout = TemplateLayout::initial();
        $layout['front']['elements'][0]['x'] = 84.0;
        $this->expectException(ValidationException::class);
        TemplateLayout::normalize($layout, 85.60, 53.98);
    }
}
