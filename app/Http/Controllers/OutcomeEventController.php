<?php

namespace App\Http\Controllers;

use App\Enums\OutcomeStatus;
use App\Http\Requests\StoreOutcomeEventRequest;
use App\Models\OutcomeEvent;
use App\Models\Property;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OutcomeEventController extends Controller
{
    public function create(): View
    {
        return view('outcomes.create', [
            'properties' => Property::with('client')->orderBy('client_id')->orderBy('id')->get(),
            'statuses' => OutcomeStatus::cases(),
        ]);
    }

    public function store(StoreOutcomeEventRequest $request): RedirectResponse
    {
        $event = OutcomeEvent::create($request->validated());

        return redirect()
            ->route('clients.show', $event->property->client_id)
            ->with('status', "Recorded a {$event->status->value} outcome for property {$event->property_id}.");
    }
}
