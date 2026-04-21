<?php

use Denosys\Analytics\PanConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => PanConfiguration::reset())
    ->in('Feature', 'Unit');
