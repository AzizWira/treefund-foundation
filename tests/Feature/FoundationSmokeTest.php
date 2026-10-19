<?php
namespace Tests\Feature;
use Tests\TestCase;
class FoundationSmokeTest extends TestCase {
    public function test_foundation_home_renders(): void {
        $this->get('/')->assertOk()->assertSee('TreeFund');
    }
}
