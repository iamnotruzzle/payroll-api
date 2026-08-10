<?php

namespace Tests\Unit;

use App\Support\Hris\PdsPrintPresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PdsPrintPresenterTest extends TestCase
{
    #[DataProvider('legacyEducationLevels')]
    public function test_it_maps_legacy_numeric_education_levels(string $value, string $expected): void
    {
        $reflection = new ReflectionClass(PdsPrintPresenter::class);
        $presenter = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('matchesEducationLevel');

        $levels = ['ELEMENTARY', 'SECONDARY', 'VOCATIONAL', 'COLLEGE', 'GRADUATE STUDIES'];

        foreach ($levels as $level) {
            $this->assertSame($level === $expected, $method->invoke($presenter, $value, $level));
        }
    }

    public static function legacyEducationLevels(): array
    {
        return [
            'elementary' => ['0', 'ELEMENTARY'],
            'high school' => ['1', 'SECONDARY'],
            'vocational' => ['2', 'VOCATIONAL'],
            'college' => ['3', 'COLLEGE'],
            'graduate studies' => ['4', 'GRADUATE STUDIES'],
        ];
    }

    #[DataProvider('legacyEducationLabels')]
    public function test_it_prints_readable_labels_for_legacy_education_levels(string $value, string $expected): void
    {
        $reflection = new ReflectionClass(PdsPrintPresenter::class);
        $presenter = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('educationLevelLabel');

        $this->assertSame($expected, $method->invoke($presenter, $value));
    }

    public static function legacyEducationLabels(): array
    {
        return [
            ['0', 'ELEMENTARY'],
            ['1', 'SECONDARY'],
            ['2', 'VOCATIONAL / TRADE COURSE'],
            ['3', 'COLLEGE'],
            ['4', 'GRADUATE STUDIES'],
        ];
    }
}
