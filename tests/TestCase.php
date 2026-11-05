<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Seed jurisdictions whenever a test refreshes the database. */
    protected bool $seed = true;
}
