<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); 
    }

    public function test_home_requires_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }
}