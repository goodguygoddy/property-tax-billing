@use('App\Support\Money')
@props(['record'])
{{-- $record is an InvoiceLine or Adjustment: both carry the same fee computation columns. --}}
<details class="breakdown">
    <summary>How this fee was calculated</summary>
    <table class="compact">
        <thead>
            <tr>
                <th>Jurisdiction</th>
                <th class="num">Rate / $100</th>
                <th class="num">Taxable at noticed</th>
                <th class="num">Tax at noticed</th>
                <th class="num">Taxable at final</th>
                <th class="num">Tax at final</th>
                <th class="num">Savings</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($record->tax_breakdown as $row)
                <tr>
                    <td>{{ $row['code'] }}</td>
                    <td class="num">{{ $row['rate'] }}</td>
                    <td class="num">{{ Money::decimal($row['taxable_at_noticed']) }}</td>
                    <td class="num">{{ Money::decimal($row['tax_at_noticed']) }}</td>
                    <td class="num">{{ Money::decimal($row['taxable_at_final']) }}</td>
                    <td class="num">{{ Money::decimal($row['tax_at_final']) }}</td>
                    <td class="num">{{ Money::decimal($row['savings']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6">Total savings (noticed value {{ Money::decimal($record->noticed_value) }} → final value {{ Money::decimal($record->final_value) }})</th>
                <th class="num">{{ Money::decimal($record->savings) }}</th>
            </tr>
        </tfoot>
    </table>
    <ol class="steps">
        <li>Fee rate {{ $record->feeRatePercent() }} of savings = <strong>{{ Money::decimal($record->raw_fee) }}</strong> before rounding</li>
        <li>Rounded to the cent (halves up): <strong>{{ Money::decimal($record->raw_fee, 2) }}</strong></li>
        @if ($record->waived)
            <li>Below the {{ Money::decimal(config('billing.minimum_fee')) }} minimum, so the fee is <strong>waived</strong></li>
        @elseif ($record->capped)
            <li>Above the {{ Money::decimal(config('billing.maximum_fee')) }} maximum, so the fee is <strong>capped</strong></li>
        @elseif ($record->fee_cents === 0)
            <li>No savings, so no fee</li>
        @endif
        <li>Fee charged: <strong>{{ Money::cents($record->fee_cents) }}</strong></li>
    </ol>
</details>
