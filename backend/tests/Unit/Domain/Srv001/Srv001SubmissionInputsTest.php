<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Srv001;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001SubmissionInputs;
use PHPUnit\Framework\TestCase;

/**
 * TD-01 · Srv001SubmissionInputs::fromApplicationData shape.
 */
class Srv001SubmissionInputsTest extends TestCase
{
    public function test_parses_pilot_form_shape(): void
    {
        $data = [
            'project_sector'                  => 'خاص',
            'governorate'                     => 'AMMAN',
            'basin_number'                    => '00123',
            'parcel_number'                   => '0456',
            'basin_or_location_name'          => 'حوض الاختبار',
            'contract_owner_name'             => 'أحمد المقاول',
            'floor_count'                     => 3,
            'floor_area'                      => 500,
            'land_or_contract_area'           => 800,
            'proposed_built_area'             => 700.5,
            'actual_exploration_point_count'  => 3,
            'building_count'                  => 1,
            'has_partial_basement'            => 'yes',
            'basement_area_m2'                => 40,
            'roof_area_m2'                    => 25,
        ];

        $in = Srv001SubmissionInputs::fromApplicationData($data);

        $this->assertSame('خاص', $in->projectSector);
        $this->assertSame('00123', $in->basinNumber, 'basin_number must remain a string (leading zeros preserved)');
        $this->assertSame('0456', $in->parcelNumber);
        $this->assertSame(3, $in->floorCount);
        $this->assertSame(500.0, $in->floorArea);
        $this->assertSame(700.5, $in->proposedBuiltArea);
        $this->assertTrue($in->hasPartialBasement);
    }

    public function test_missing_fields_are_null_not_zero(): void
    {
        $in = Srv001SubmissionInputs::fromApplicationData([]);
        $this->assertNull($in->floorCount);
        $this->assertNull($in->floorArea);
        $this->assertNull($in->actualExplorationPointCount);
        $this->assertNull($in->hasPartialBasement);
    }

    public function test_has_partial_basement_no_parses_to_false(): void
    {
        $in = Srv001SubmissionInputs::fromApplicationData(['has_partial_basement' => 'no']);
        $this->assertFalse($in->hasPartialBasement);
    }

    public function test_non_numeric_input_becomes_null_not_zero(): void
    {
        $in = Srv001SubmissionInputs::fromApplicationData([
            'floor_count' => 'three',
            'floor_area'  => 'wide',
        ]);
        $this->assertNull($in->floorCount);
        $this->assertNull($in->floorArea);
    }

    public function test_empty_strings_become_null(): void
    {
        $in = Srv001SubmissionInputs::fromApplicationData([
            'governorate'        => '',
            'basin_number'       => '',
            'contract_owner_name' => '',
        ]);
        $this->assertNull($in->governorate);
        $this->assertNull($in->basinNumber);
        $this->assertNull($in->contractOwnerName);
    }
}
