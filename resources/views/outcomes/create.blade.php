<x-layout title="Record outcome">
    <h1>Record an outcome</h1>
    <p class="muted intro">Outcomes entered here are billed on the next run, the same as imported events. They have no source id.</p>

    @if ($errors->any())
        <div class="errors">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('outcomes.store') }}" class="form">
        @csrf

        <label>
            Property
            <select name="property_id" required>
                <option value="">Choose a property…</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}" @selected(old('property_id') === $property->id)>
                        {{ $property->id }} (client {{ $property->client_id }})
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            Recorded on
            <input type="date" name="recorded_at" value="{{ old('recorded_at', now()->toDateString()) }}" required>
        </label>

        <label>
            Status
            <select name="status" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', 'final') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Noticed value ($)
            <input type="number" name="noticed_value" step="0.01" min="0" value="{{ old('noticed_value') }}" required>
        </label>

        <label>
            Final value ($) <span class="muted">— leave blank while pending</span>
            <input type="number" name="final_value" step="0.01" min="0" value="{{ old('final_value') }}">
        </label>

        <button type="submit">Record outcome</button>
    </form>
</x-layout>
