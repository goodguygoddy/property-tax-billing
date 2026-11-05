<?php

namespace App\Console\Commands;

use App\Import\ImportException;
use App\Import\JsonImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportDataCommand extends Command
{
    protected $signature = 'billing:import {file : Path to a JSON file of properties and outcome events}';

    protected $description = 'Import properties and outcome events from a JSON file';

    public function handle(JsonImporter $importer): int
    {
        $path = $this->resolvePath($this->argument('file'));

        try {
            $result = $importer->import($path);
        } catch (ImportException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (ValidationException $e) {
            $this->components->error('The file failed validation; nothing was imported.');

            foreach ($e->validator->errors()->all() as $message) {
                $this->components->bulletList([$message]);
            }

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('New clients', (string) $result->clients);
        $this->components->twoColumnDetail('Properties imported or updated', (string) $result->properties);
        $this->components->twoColumnDetail('Events imported', (string) $result->eventsImported);
        $this->components->twoColumnDetail('Events already present (skipped)', (string) $result->eventsSkipped);

        foreach ($result->warnings as $warning) {
            $this->components->warn($warning);
        }

        $this->components->info("Imported {$path}");

        return self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        return file_exists($file) ? $file : base_path($file);
    }
}
