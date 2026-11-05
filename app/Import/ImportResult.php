<?php

namespace App\Import;

final class ImportResult
{
    public int $clients = 0;

    public int $properties = 0;

    public int $eventsImported = 0;

    public int $eventsSkipped = 0;

    /** @var list<string> */
    public array $warnings = [];
}
