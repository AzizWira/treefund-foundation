<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_php_runtime_is_supported(): void
    {
        $this->assertGreaterThanOrEqual(80300, PHP_VERSION_ID);
    }
}
