<?php

namespace App\Http\Controllers;

use App\Billing\FeeCalculator;
use App\Models\Client;
use App\Models\OutcomeEvent;
use Illuminate\Contracts\View\View;

class ClientController extends Controller
{
    public function index(FeeCalculator $fees): View
    {
        $clients = Client::query()
            ->withCount(['properties', 'invoices', 'adjustments'])
            ->withSum('invoices', 'total_cents')
            ->withSum('adjustments', 'amount_cents')
            ->orderBy('id')
            ->get();

        return view('clients.index', [
            'clients' => $clients,
            'fees' => $fees,
        ]);
    }

    public function show(Client $client, FeeCalculator $fees): View
    {
        $client->load([
            'properties.jurisdictions',
            'properties.invoiceLine.invoice',
            'invoices.lines.property',
            'invoices.lines.outcomeEvent',
            'invoices.lines.adjustments',
            'adjustments.invoice',
            'adjustments.property',
            'adjustments.outcomeEvent',
        ]);

        $events = OutcomeEvent::query()
            ->whereIn('property_id', $client->properties->pluck('id'))
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        $latestOutcomes = $events->sortByDesc(fn (OutcomeEvent $event) => [$event->recorded_at->timestamp, $event->id])
            ->unique('property_id')
            ->keyBy('property_id');

        return view('clients.show', [
            'client' => $client,
            'events' => $events,
            'latestOutcomes' => $latestOutcomes,
            'feeRate' => $fees->rateFor($client->properties->count()),
            'balanceCents' => $client->invoices->sum('total_cents') + $client->adjustments->sum('amount_cents'),
        ]);
    }
}
