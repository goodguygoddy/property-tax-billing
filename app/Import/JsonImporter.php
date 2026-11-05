<?php

namespace App\Import;

use App\Enums\OutcomeStatus;
use App\Models\Client;
use App\Models\Jurisdiction;
use App\Models\OutcomeEvent;
use App\Models\Property;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Imports properties and outcome events from a JSON file. Safe to re-run:
 * clients and properties are upserted, events are matched on source_id and
 * never overwritten once stored.
 */
class JsonImporter
{
    public function import(string $path): ImportResult
    {
        $data = $this->decode($path);

        return DB::transaction(function () use ($data) {
            $result = new ImportResult;

            $this->validateProperties($data['properties'] ?? []);
            $this->importProperties($data['properties'] ?? [], $result);

            $this->validateEvents($data['events'] ?? []);
            $this->importEvents($data['events'] ?? [], $result);

            return $result;
        });
    }

    /**
     * @return array{properties?: array, events?: array}
     */
    private function decode(string $path): array
    {
        if (! is_readable($path)) {
            throw new ImportException("File not found or not readable: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new ImportException('File does not contain valid JSON: '.json_last_error_msg());
        }

        if (Jurisdiction::count() === 0) {
            throw new ImportException('No jurisdictions are configured. Run "php artisan db:seed" first.');
        }

        return $data;
    }

    private function validateProperties(array $properties): void
    {
        Validator::make(['properties' => $properties], [
            'properties' => ['array'],
            'properties.*.id' => ['required', 'string', 'max:64', 'distinct'],
            'properties.*.client_id' => ['required', 'string', 'max:64'],
            'properties.*.homestead' => ['required', 'boolean'],
            'properties.*.jurisdictions' => ['required', 'array', 'min:1'],
            'properties.*.jurisdictions.*' => ['required', 'string', Rule::exists('jurisdictions', 'code')],
        ])->validate();
    }

    private function importProperties(array $properties, ImportResult $result): void
    {
        foreach ($properties as $row) {
            $client = Client::firstOrCreate(['id' => $row['client_id']]);

            if ($client->wasRecentlyCreated) {
                $result->clients++;
            }

            $property = Property::updateOrCreate(
                ['id' => $row['id']],
                ['client_id' => $client->id, 'homestead' => (bool) $row['homestead']],
            );
            $property->jurisdictions()->sync($row['jurisdictions']);

            $result->properties++;
        }
    }

    private function validateEvents(array $events): void
    {
        Validator::make(['events' => $events], [
            'events' => ['array'],
            'events.*.source_id' => ['required', 'string', 'max:64', 'distinct'],
            'events.*.property_id' => ['required', 'string', Rule::exists('properties', 'id')],
            'events.*.recorded_at' => ['required', 'date'],
            'events.*.status' => ['required', Rule::enum(OutcomeStatus::class)],
            'events.*.noticed_value' => ['required', 'numeric', 'min:0'],
            'events.*.final_value' => ['nullable', 'numeric', 'min:0'],
        ], [
            'events.*.property_id.exists' => 'The :attribute references a property that is not in this file or the database.',
        ])->after(function ($validator) use ($events) {
            foreach ($events as $index => $event) {
                $status = $event['status'] ?? null;
                $hasFinal = isset($event['final_value']);

                if ($status === OutcomeStatus::Final->value && ! $hasFinal) {
                    $validator->errors()->add("events.{$index}.final_value", 'A final outcome must have a final value.');
                }

                if ($status === OutcomeStatus::Pending->value && $hasFinal) {
                    $validator->errors()->add("events.{$index}.final_value", 'A pending outcome cannot have a final value.');
                }
            }
        })->validate();
    }

    private function importEvents(array $events, ImportResult $result): void
    {
        foreach ($events as $row) {
            $existing = OutcomeEvent::where('source_id', $row['source_id'])->first();

            if ($existing !== null) {
                $result->eventsSkipped++;

                if ($this->differs($existing, $row)) {
                    $result->warnings[] = "Event {$row['source_id']} already exists with different data; the stored version was kept.";
                }

                continue;
            }

            OutcomeEvent::create([
                'property_id' => $row['property_id'],
                'source_id' => $row['source_id'],
                'recorded_at' => $row['recorded_at'],
                'status' => $row['status'],
                'noticed_value' => $row['noticed_value'],
                'final_value' => $row['final_value'] ?? null,
            ]);

            $result->eventsImported++;
        }
    }

    private function differs(OutcomeEvent $existing, array $row): bool
    {
        return $existing->property_id !== $row['property_id']
            || $existing->status->value !== $row['status']
            || ! $existing->recorded_at->isSameDay($row['recorded_at'])
            || ! BigDecimal::of($existing->noticed_value)->isEqualTo((string) $row['noticed_value'])
            || ! BigDecimal::of($existing->final_value ?? '0')->isEqualTo((string) ($row['final_value'] ?? '0'));
    }
}
