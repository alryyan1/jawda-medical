<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\DeviceChildTestNormalRange;

class DeviceChildTestNormalRangeTest extends TestCase
{
    /** @test */
    public function it_casts_is_default_to_a_boolean()
    {
        // 1. Create the model instance manually
        $record = new DeviceChildTestNormalRange([
            'is_default' => 1
        ]);

        // 2. Assertions
        // We check that even though we gave it 1, Eloquent gives us back 'true'
        $this->assertIsBool($record->is_default);
        $this->assertTrue($record->is_default);
        
        // This check confirms it's NOT just the integer 1 (strict check)
        $this->assertSame(true, $record->is_default);
    }
}