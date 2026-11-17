<?php
namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Validates saved design data. Coordinates are millimeters, font sizes points. */
final class TemplateLayout
{
    private const BINDINGS = [
        'full_name', 'first_name', 'last_name', 'employee_code', 'job_title',
        'department', 'branch', 'email', 'company_name', 'issue_date',
        'photo', 'logo', 'verification_url', 'expiry_date',
    ];

    public static function initial(float $cardW = 85.60, float $cardH = 53.98): array
    {
        // All presets are defined in CR80 coordinates; adapt them for custom physical sizes.
        $layout = [
            'front' => ['background_asset_id' => null, 'logo_asset_id' => null, 'elements' => [
                ['id'=>'company', 'type'=>'text','binding'=>'company_name','text'=>'','x'=>6,'y'=>3,'w'=>73,'h'=>8,'fontSize'=>17,'fontFamily'=>'Arial','bold'=>true,'italic'=>false,'color'=>'#0f172a','align'=>'center','locked'=>false,'z'=>1],
                ['id'=>'photo', 'type'=>'image','binding'=>'photo','text'=>'','x'=>6,'y'=>15,'w'=>23,'h'=>30,'fontSize'=>11,'fontFamily'=>'Arial','bold'=>false,'italic'=>false,'color'=>'#0f172a','align'=>'center','locked'=>false,'z'=>2],
                ['id'=>'name', 'type'=>'text','binding'=>'full_name','text'=>'','x'=>32,'y'=>20,'w'=>47,'h'=>7,'fontSize'=>13,'fontFamily'=>'Arial','bold'=>true,'italic'=>false,'color'=>'#0f172a','align'=>'left','locked'=>false,'z'=>3],
                ['id'=>'role', 'type'=>'text','binding'=>'job_title','text'=>'','x'=>32,'y'=>29,'w'=>47,'h'=>6,'fontSize'=>10,'fontFamily'=>'Arial','bold'=>false,'italic'=>false,'color'=>'#334155','align'=>'left','locked'=>false,'z'=>4],
                ['id'=>'number', 'type'=>'text','binding'=>'employee_code','text'=>'','x'=>32,'y'=>37,'w'=>47,'h'=>6,'fontSize'=>10,'fontFamily'=>'Arial','bold'=>false,'italic'=>false,'color'=>'#334155','align'=>'left','locked'=>false,'z'=>5],
            ]],
            'back' => ['background_asset_id' => null, 'logo_asset_id' => null, 'elements' => [
                ['id'=>'qr', 'type'=>'qr','binding'=>'verification_url','text'=>'','x'=>31,'y'=>8,'w'=>24,'h'=>24,'fontSize'=>10,'fontFamily'=>'Arial','bold'=>false,'italic'=>false,'color'=>'#0f172a','align'=>'center','locked'=>false,'z'=>1],
                ['id'=>'notice', 'type'=>'text','binding'=>'custom','text'=>'If found, please return this card to the issuing company.','x'=>8,'y'=>36,'w'=>69,'h'=>11,'fontSize'=>8,'fontFamily'=>'Arial','bold'=>false,'italic'=>false,'color'=>'#334155','align'=>'center','locked'=>false,'z'=>2],
            ]],
        ];
        // Preserve the original exact CR80 template for existing records.
        if (abs($cardW - 85.60) < 0.00001 && abs($cardH - 53.98) < 0.00001) {
            return $layout;
        }

        $factorX = $cardW / 85.60;
        $factorY = $cardH / 53.98;
        $fontFactor = min($factorX, $factorY);
        foreach (['front', 'back'] as $side) {
            foreach ($layout[$side]['elements'] as &$element) {
                foreach (['x', 'w'] as $key) {
                    $element[$key] = round($element[$key] * $factorX, 2);
                }
                foreach (['y', 'h'] as $key) {
                    $element[$key] = round($element[$key] * $factorY, 2);
                }
                $element['fontSize'] = round(min(42, max(5, $element['fontSize'] * $fontFactor)), 1);
            }
            unset($element);
        }
        return $layout;
    }

    public static function normalize(mixed $value, float $cardW, float $cardH): array
    {
        if (!is_array($value) || count($value) !== 2 || !isset($value['front'], $value['back'])) {
            throw ValidationException::withMessages(['layout'=>'The layout must contain front and back sides.']);
        }
        $result = [];
        foreach (['front','back'] as $side) {
            $page = $value[$side] ?? null;
            if (!is_array($page) || !array_key_exists('elements', $page) || !is_array($page['elements'])) {
                throw ValidationException::withMessages(['layout'=>"Invalid {$side} layout."]);
            }
            if (count($page['elements']) > config('studio.max_elements_per_side', 60)) {
                throw ValidationException::withMessages(['layout'=>"Too many {$side} elements."]);
            }
            $assetId = $page['background_asset_id'] ?? null;
            if ($assetId !== null && (!is_int($assetId) || $assetId < 1)) {
                throw ValidationException::withMessages(['layout'=>"Invalid {$side} background."]);
            }
             $logoId = $page['logo_asset_id'] ?? null;
            if ($logoId !== null && (!is_int($logoId) || $logoId < 1)) {
                throw ValidationException::withMessages(['layout'=>"Invalid {$side} logo."]);
            }
            $out = []; $ids = [];
            foreach ($page['elements'] as $e) {
                if (!is_array($e)) { throw ValidationException::withMessages(['layout'=>'Invalid element.']); }
                $id = $e['id'] ?? null;
                $type = $e['type'] ?? null;
                $binding = $e['binding'] ?? null;
                if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,48}$/D', $id) || isset($ids[$id])) {
                    throw ValidationException::withMessages(['layout'=>'Duplicate or invalid element ID.']);
                }
                $ids[$id] = true;
                if (!in_array($type, ['text','image','qr'], true)) { throw ValidationException::withMessages(['layout'=>'Invalid element type.']); }
                if (!is_string($binding) || !in_array($binding, [...self::BINDINGS, 'custom'], true)) {
                    throw ValidationException::withMessages(['layout'=>'Invalid field binding.']);
                }
                if ($type === 'image' && !in_array($binding, ['photo','logo'], true)) { throw ValidationException::withMessages(['layout'=>'Invalid image binding.']); }
                if ($type === 'qr' && $binding !== 'verification_url') { throw ValidationException::withMessages(['layout'=>'QR must use verification_url.']); }
                if ($type === 'text' && in_array($binding, ['photo','logo','verification_url'], true)) { throw ValidationException::withMessages(['layout'=>'Invalid text binding.']); }
                $geometry = [];
                foreach (['x', 'y', 'w', 'h'] as $key) {
                    if (!isset($e[$key]) || !is_numeric($e[$key]) || !is_finite((float)$e[$key])) {
                        throw ValidationException::withMessages(['layout'=>"Invalid {$key} position."]);
                    }
                    $geometry[$key] = round((float)$e[$key], 2);
                }
                if ($geometry['x'] < 0 || $geometry['y'] < 0 || $geometry['w'] < 2 || $geometry['h'] < 2 ||
                    $geometry['x'] + $geometry['w'] > $cardW + 0.01 || $geometry['y'] + $geometry['h'] > $cardH + 0.01) {
                    throw ValidationException::withMessages(['layout'=>'Element extends outside the card.']);
                }
                $fontSize = (float)($e['fontSize'] ?? 11);
                $font = (string)($e['fontFamily'] ?? 'Arial');
                $color = (string)($e['color'] ?? '#0f172a');
                $align = (string)($e['align'] ?? 'left');
                if ($fontSize < 5 || $fontSize > 42 || !in_array($font, config('studio.font_families'), true) ||
                    !preg_match('/^#[0-9a-fA-F]{6}$/D', $color) || !in_array($align, ['left','center','right'], true)) {
                    throw ValidationException::withMessages(['layout'=>'Invalid font properties.']);
                }
                $text = (string)($e['text'] ?? '');
                if (mb_strlen($text) > 240) { throw ValidationException::withMessages(['layout'=>'Custom text is too long.']); }
                $out[] = array_merge(['id'=>$id,'type'=>$type,'binding'=>$binding,'text'=>$text], $geometry, [
                    'fontSize'=>round($fontSize, 1),'fontFamily'=>$font,
                    'bold'=>($e['bold'] ?? false) === true,'italic'=>($e['italic'] ?? false) === true,
                    'color'=>$color,'align'=>$align, 'locked'=>($e['locked'] ?? false) === true,
                    'z'=>min(999, max(0, (int)($e['z'] ?? 1))),
                ]);
            }
            $result[$side] = ['background_asset_id'=>$assetId,'logo_asset_id'=>$logoId,'elements'=>$out];
        }
        return $result;
    }
}
