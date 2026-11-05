@use('App\Support\Money')
<x-layout :title="'Client '.$client->id">
    <p class="crumbs"><a href="{{ route('clients.index') }}">Clients</a> / {{ $client->id }}</p>
    <h1>Client {{ $client->id }}</h1>

    <dl class="stats">
        <div><dt>Properties enrolled</dt><dd>{{ $client->properties->count() }}</dd></div>
        <div><dt>Fee rate</dt><dd>{{ $feeRate->multipliedBy(100)->toScale(0) }}% of savings</dd></div>
        <div><dt>Invoiced</dt><dd>{{ Money::cents($client->invoices->sum('total_cents')) }}</dd></div>
        <div><dt>Adjustments</dt><dd>{{ Money::cents($client->adjustments->sum('amount_cents'), signed: true) }}</dd></div>
        <div><dt>Net billed</dt><dd>{{ Money::cents($balanceCents) }}</dd></div>
    </dl>

    <section>
        <h2>Properties</h2>
        <table>
            <thead>
                <tr>
                    <th>Property</th>
                    <th>Homestead</th>
                    <th>Jurisdictions</th>
                    <th>Latest outcome</th>
                    <th>Billing status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($client->properties as $property)
                    @php($latest = $latestOutcomes->get($property->id))
                    <tr>
                        <td>{{ $property->id }}</td>
                        <td>{{ $property->homestead ? 'Yes' : 'No' }}</td>
                        <td>{{ $property->jurisdictions->pluck('code')->join(', ') }}</td>
                        <td>
                            @if ($latest)
                                <span class="badge badge-{{ $latest->status->value }}">{{ $latest->status->label() }}</span>
                                {{ Money::decimal($latest->noticed_value) }}
                                @if ($latest->final_value !== null) → {{ Money::decimal($latest->final_value) }} @endif
                                <span class="muted">({{ $latest->recorded_at->toDateString() }})</span>
                            @else
                                <span class="muted">No outcome recorded</span>
                            @endif
                        </td>
                        <td>
                            @if ($property->invoiceLine)
                                Billed on {{ $property->invoiceLine->invoice->number }}
                            @else
                                <span class="muted">Not yet billed</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section>
        <h2>Invoices</h2>
        @forelse ($client->invoices->sortBy('id') as $invoice)
            <article class="document">
                <header>
                    <h3>{{ $invoice->number }}</h3>
                    <span>Issued {{ $invoice->issued_on->toDateString() }} · run #{{ $invoice->billing_run_id }}</span>
                    <strong class="total">{{ Money::cents($invoice->total_cents) }}</strong>
                </header>
                <table>
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th class="num">Noticed value</th>
                            <th class="num">Final value</th>
                            <th class="num">Tax savings</th>
                            <th class="num">Rate</th>
                            <th class="num">Fee</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->lines as $line)
                            <tr>
                                <td>{{ $line->property_id }}</td>
                                <td class="num">{{ Money::decimal($line->noticed_value) }}</td>
                                <td class="num">{{ Money::decimal($line->final_value) }}</td>
                                <td class="num">{{ Money::decimal($line->savings) }}</td>
                                <td class="num">{{ $line->feeRatePercent() }}</td>
                                <td class="num">{{ Money::cents($line->fee_cents) }}</td>
                                <td>
                                    @if ($line->waived) <span class="badge">Waived</span> @endif
                                    @if ($line->capped) <span class="badge">Capped</span> @endif
                                    @if ($line->adjustments->isNotEmpty())
                                        <span class="badge">Adjusted by {{ $line->adjustments->map->number->join(', ') }}</span>
                                    @endif
                                    <span class="muted">Outcome {{ $line->outcomeEvent->source_id ?? '#'.$line->outcomeEvent->id }}</span>
                                </td>
                            </tr>
                            <tr class="detail-row">
                                <td colspan="7"><x-fee-breakdown :record="$line" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </article>
        @empty
            <p class="muted">No invoices have been issued for this client.</p>
        @endforelse
    </section>

    <section>
        <h2>Adjustments</h2>
        @forelse ($client->adjustments->sortBy('id') as $adjustment)
            <article class="document">
                <header>
                    <h3>{{ $adjustment->number }} <span class="muted">({{ $adjustment->isCredit() ? 'credit' : 'charge' }})</span></h3>
                    <span>Issued {{ $adjustment->issued_on->toDateString() }} · run #{{ $adjustment->billing_run_id }} · adjusts {{ $adjustment->invoice->number }}</span>
                    <strong class="total">{{ Money::cents($adjustment->amount_cents, signed: true) }}</strong>
                </header>
                <table>
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th class="num">Revised final value</th>
                            <th class="num">Tax savings</th>
                            <th class="num">Rate</th>
                            <th class="num">Previously billed</th>
                            <th class="num">Revised fee</th>
                            <th class="num">Difference</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $adjustment->property_id }}</td>
                            <td class="num">{{ Money::decimal($adjustment->final_value) }}</td>
                            <td class="num">{{ Money::decimal($adjustment->savings) }}</td>
                            <td class="num">{{ $adjustment->feeRatePercent() }}</td>
                            <td class="num">{{ Money::cents($adjustment->previous_fee_cents) }}</td>
                            <td class="num">{{ Money::cents($adjustment->fee_cents) }}</td>
                            <td class="num"><strong>{{ Money::cents($adjustment->amount_cents, signed: true) }}</strong></td>
                            <td>
                                @if ($adjustment->waived) <span class="badge">Waived</span> @endif
                                @if ($adjustment->capped) <span class="badge">Capped</span> @endif
                                <span class="muted">Outcome {{ $adjustment->outcomeEvent->source_id ?? '#'.$adjustment->outcomeEvent->id }}</span>
                            </td>
                        </tr>
                        <tr class="detail-row">
                            <td colspan="8"><x-fee-breakdown :record="$adjustment" /></td>
                        </tr>
                    </tbody>
                </table>
            </article>
        @empty
            <p class="muted">No adjustments have been issued for this client.</p>
        @endforelse
    </section>

    <section>
        <h2>Outcome events</h2>
        <table>
            <thead>
                <tr>
                    <th>Recorded</th>
                    <th>Property</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th class="num">Noticed value</th>
                    <th class="num">Final value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    <tr>
                        <td>{{ $event->recorded_at->toDateString() }}</td>
                        <td>{{ $event->property_id }}</td>
                        <td>{{ $event->source_id ?? 'Entered manually (#'.$event->id.')' }}</td>
                        <td><span class="badge badge-{{ $event->status->value }}">{{ $event->status->label() }}</span></td>
                        <td class="num">{{ Money::decimal($event->noticed_value) }}</td>
                        <td class="num">{{ $event->final_value !== null ? Money::decimal($event->final_value) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No outcome events recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layout>
