<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The home page sends visitors to the login screen.
     *
     * @return void
     */
    public function testHomeRedirectsToLogin()
    {
        $this->get('/')->assertRedirect('/login');
    }
}
