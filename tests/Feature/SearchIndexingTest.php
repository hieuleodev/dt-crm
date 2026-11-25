<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchIndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_tell_search_engines_not_to_index(): void
    {
        $this->withoutVite();

        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex')
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">', false);
    }

    public function test_redirects_and_errors_also_carry_the_noindex_header(): void
    {
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');
        $this->get('/trang-khong-ton-tai')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');
    }
}
