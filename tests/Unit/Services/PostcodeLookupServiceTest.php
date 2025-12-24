<?php

namespace Tests\Unit\Services;

use App\Services\PostcodeLookupService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PostcodeLookupServiceTest extends TestCase
{
    private PostcodeLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PostcodeLookupService();
    }

    /** @test */
    public function it_normalizes_lowercase_postcode()
    {
        $result = $this->service->normalizePostcode('sw1a 1aa');

        $this->assertEquals('SW1A 1AA', $result);
    }

    /** @test */
    public function it_normalizes_postcode_without_space()
    {
        $result = $this->service->normalizePostcode('SW1A1AA');

        $this->assertEquals('SW1A 1AA', $result);
    }

    /** @test */
    public function it_normalizes_postcode_with_multiple_spaces()
    {
        $result = $this->service->normalizePostcode('SW1A   1AA');

        $this->assertEquals('SW1A 1AA', $result);
    }

    /** @test */
    public function it_pads_short_postcode_to_8_characters()
    {
        $result = $this->service->normalizePostcode('M1 1AA');

        $this->assertEquals('M1 1AA  ', $result);
        $this->assertEquals(8, strlen($result));
    }

    /** @test */
    public function it_handles_postcode_with_single_letter_area()
    {
        $result = $this->service->normalizePostcode('m11aa');

        $this->assertEquals('M1 1AA  ', $result);
    }

    /** @test */
    public function it_handles_postcode_with_two_letter_area()
    {
        $result = $this->service->normalizePostcode('ec1a1bb');

        $this->assertEquals('EC1A 1BB', $result);
    }

    /** @test */
    public function it_handles_postcode_with_letter_in_district()
    {
        $result = $this->service->normalizePostcode('w1a0ax');

        $this->assertEquals('W1A 0AX ', $result);
    }

    /** @test */
    public function it_validates_correct_postcode_formats()
    {
        $validPostcodes = [
            'SW1A 1AA',
            'M1 1AA  ',
            'B33 8TH ',
            'CR2 6XH ',
            'DN55 1PT',
            'W1A 0AX ',
            'EC1A 1BB',
        ];

        foreach ($validPostcodes as $postcode) {
            // Should not throw exception
            $this->service->normalizePostcode($postcode);
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function it_throws_exception_for_too_short_postcode()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid postcode format: must be between 5 and 8 characters');

        $normalized = $this->service->normalizePostcode('ABC');

        // Use reflection to call private validatePostcode method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validatePostcode');
        $method->setAccessible(true);
        $method->invoke($this->service, $normalized);
    }

    /** @test */
    public function it_throws_exception_for_invalid_postcode_pattern()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid postcode format: does not match UK postcode pattern');

        $normalized = $this->service->normalizePostcode('12345');

        // Use reflection to call private validatePostcode method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validatePostcode');
        $method->setAccessible(true);
        $method->invoke($this->service, $normalized);
    }

    /** @test */
    public function it_throws_exception_for_postcode_with_invalid_characters()
    {
        $this->expectException(InvalidArgumentException::class);

        $normalized = $this->service->normalizePostcode('SW1@ 1AA');

        // Use reflection to call private validatePostcode method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validatePostcode');
        $method->setAccessible(true);
        $method->invoke($this->service, $normalized);
    }

    /** @test */
    public function it_normalizes_various_input_formats_consistently()
    {
        $inputs = [
            'sw1a1aa',
            'SW1A1AA',
            'sw1a 1aa',
            'SW1A 1AA',
            'SW1A  1AA',
        ];

        $expected = 'SW1A 1AA';

        foreach ($inputs as $input) {
            $result = $this->service->normalizePostcode($input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }
}
