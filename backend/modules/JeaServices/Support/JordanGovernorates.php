<?php

declare(strict_types=1);

namespace Modules\JeaServices\Support;

/**
 * JordanGovernorates — canonical 12-option governorate list.
 *
 * Extracted from DrawingFeeMatrixSeeder (JORD-63) so SRV-001 and any
 * future service that needs the same select can consume the same list
 * without a second independent copy. Enum-in-schema pattern; no
 * governorates table.
 */
final class JordanGovernorates
{
    /**
     * @return list<array{value: string, label_ar: string, label_en: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => 'amman',   'label_ar' => 'عمان',    'label_en' => 'Amman'],
            ['value' => 'irbid',   'label_ar' => 'إربد',    'label_en' => 'Irbid'],
            ['value' => 'zarqa',   'label_ar' => 'الزرقاء', 'label_en' => 'Zarqa'],
            ['value' => 'mafraq',  'label_ar' => 'المفرق',  'label_en' => 'Mafraq'],
            ['value' => 'balqa',   'label_ar' => 'البلقاء', 'label_en' => 'Balqa'],
            ['value' => 'karak',   'label_ar' => 'الكرك',   'label_en' => 'Karak'],
            ['value' => 'maan',    'label_ar' => 'معان',    'label_en' => "Ma'an"],
            ['value' => 'tafilah', 'label_ar' => 'الطفيلة', 'label_en' => 'Tafilah'],
            ['value' => 'aqaba',   'label_ar' => 'العقبة',  'label_en' => 'Aqaba'],
            ['value' => 'madaba',  'label_ar' => 'مأدبا',   'label_en' => 'Madaba'],
            ['value' => 'jerash',  'label_ar' => 'جرش',     'label_en' => 'Jerash'],
            ['value' => 'ajloun',  'label_ar' => 'عجلون',   'label_en' => 'Ajloun'],
        ];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (array $o) => $o['value'], self::options());
    }
}
