<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Nenhum teste fala com serviço de fora (o Asaas é de produção): toda chamada precisa de Http::fake.
        Http::preventStrayRequests();
    }
}
