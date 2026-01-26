<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class WithoutDatabaseTestCase extends BaseTestCase
{
    use CreatesApplication;
}
