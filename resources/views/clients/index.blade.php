@use('App\Support\Money')
<x-layout title="Clients">
    <h1>Clients</h1>

    @if ($clients->isEmpty())
        <p class="muted intro">No clients yet. Import a data file with <code>php artisan billing:import &lt;file&gt;</code>.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Client</th>
                    <th class="num">Properties</th>
                    <th class="num">Fee rate</th>
                    <th class="num">Invoices</th>
                    <th class="num">Invoiced</th>
                    <th class="num">Adjustments</th>
                    <th class="num">Net billed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clients as $client)
                    @php($invoiced = (int) $client->invoices_sum_total_cents)
                    @php($adjusted = (int) $client->adjustments_sum_amount_cents)
                    <tr>
                        <td><a href="{{ route('clients.show', $client) }}">{{ $client->id }}</a></td>
                        <td class="num">{{ $client->properties_count }}</td>
                        <td class="num">{{ $fees->rateFor($client->properties_count)->multipliedBy(100)->toScale(0) }}%</td>
                        <td class="num">{{ $client->invoices_count }}</td>
                        <td class="num">{{ Money::cents($invoiced) }}</td>
                        <td class="num">{{ $client->adjustments_count }} ({{ Money::cents($adjusted, signed: true) }})</td>
                        <td class="num"><strong>{{ Money::cents($invoiced + $adjusted) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-layout>
