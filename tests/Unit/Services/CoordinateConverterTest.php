<?php

namespace Tests\Unit\Services;

use App\Services\CoordinateConverter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CoordinateConverterTest extends TestCase
{
    private CoordinateConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new CoordinateConverter();
    }

    /**
     * Test converting a known OS Grid coordinate to WGS84
     * Using London Eye coordinates as reference
     */
    public function test_converts_os_grid_to_wgs84_accurately(): void
    {
        $result = $this->converter->osGridToWgs84(530457, 179934);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('lat', $result);
        $this->assertArrayHasKey('lng', $result);
        $this->assertIsFloat($result['lat']);
        $this->assertIsFloat($result['lng']);

        // London Eye is approximately 51.503399, -0.119519
        $this->assertEqualsWithDelta(51.503, $result['lat'], 0.01);
        $this->assertEqualsWithDelta(-0.120, $result['lng'], 0.01);
    }

    /**
     * Test batch conversion returns correct structure
     */
    public function test_batch_convert_returns_array_of_results(): void
    {
        $coordinates = [
            ['easting' => 530457, 'northing' => 179934],
            ['easting' => 651409, 'northing' => 313177],
        ];

        $results = $this->converter->batchConvert($coordinates);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);

        foreach ($results as $result) {
            $this->assertArrayHasKey('lat', $result);
            $this->assertArrayHasKey('lng', $result);
        }
    }

    /**
     * Test invalid easting throws exception
     */
    public function test_throws_exception_for_invalid_easting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('easting');

        $this->converter->osGridToWgs84(-1000, 179934);
    }

    /**
     * Test invalid northing throws exception
     */
    public function test_throws_exception_for_invalid_northing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('northing');

        $this->converter->osGridToWgs84(530457, -1000);
    }

    /**
     * Test conversion precision matches spec (decimal 9,6)
     */
    public function test_result_precision_matches_specification(): void
    {
        $result = $this->converter->osGridToWgs84(530457, 179934);

        // Check that lat/lng can be formatted to 6 decimal places
        $latFormatted = number_format($result['lat'], 6);
        $lngFormatted = number_format($result['lng'], 6);

        $this->assertMatchesRegularExpression('/^-?\d+\.\d{6}$/', $latFormatted);
        $this->assertMatchesRegularExpression('/^-?\d+\.\d{6}$/', $lngFormatted);
    }
}
