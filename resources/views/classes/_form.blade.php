{{-- Class fields, shared by classes/create and classes/edit. $class is null when creating. --}}
@php
    $class = $class ?? null;
    $endDate = old('scheduled_end_date', $class?->scheduled_end_date?->format('Y-m-d\TH:i'));
@endphp
<div class="ui-card">
    <div class="ui-card-header">
        <h2 class="ui-card-title">Basics</h2>
        <p class="ui-card-subtitle">Students see the name and description when they join.</p>
    </div>
    <div class="ui-card-body space-y-5">
        <div>
            <label for="name" class="ui-label">Class name</label>
            <input type="text" name="name" id="name" required maxlength="255" autofocus
                   value="{{ old('name', $class?->name) }}" placeholder="e.g. CS101: Programming Fundamentals"
                   class="ui-input" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name') <p id="name-error" class="ui-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="ui-label">Description <span class="ui-optional">(optional)</span></label>
            <textarea name="description" id="description" rows="4" placeholder="What the class covers and how it's graded."
                      class="ui-input" @error('description') aria-invalid="true" @enderror>{{ old('description', $class?->description) }}</textarea>
            @error('description') <p class="ui-error">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

<div class="ui-card">
    <div class="ui-card-header">
        <h2 class="ui-card-title">Certificates and schedule</h2>
        <p class="ui-card-subtitle">Decide when a student earns a certificate and when the class closes.</p>
    </div>
    <div class="ui-card-body grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="passing_threshold" class="ui-label">Passing threshold</label>
            <div class="relative">
                <input type="number" name="passing_threshold" id="passing_threshold" min="1" max="100" inputmode="numeric"
                       value="{{ old('passing_threshold', $class?->passing_threshold ?? 75) }}"
                       class="ui-input pr-9" aria-describedby="threshold-hint" @error('passing_threshold') aria-invalid="true" @enderror>
                <span class="absolute inset-y-0 right-3 flex items-center text-sm text-[#888888] pointer-events-none" aria-hidden="true">%</span>
            </div>
            <p id="threshold-hint" class="ui-hint">Share of the class's labs a student must complete to earn a certificate.</p>
            @error('passing_threshold') <p class="ui-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="scheduled_end_date" class="ui-label">End date <span class="ui-optional">(optional)</span></label>
            <input type="datetime-local" name="scheduled_end_date" id="scheduled_end_date" value="{{ $endDate }}"
                   class="ui-input" aria-describedby="end-hint" @error('scheduled_end_date') aria-invalid="true" @enderror>
            <p id="end-hint" class="ui-hint">On this date the class closes automatically and certificates are issued. Leave empty to end it yourself.</p>
            @error('scheduled_end_date') <p class="ui-error">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
