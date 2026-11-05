{{-- Card background field in the class form: a pattern, or the instructor's own picture. --}}
@php
    $savedBackground = $class?->card_image_path ? 'picture' : ($class?->card_pattern ?? 'auto');
    $background = old('card_background', $savedBackground);
    $savedImage = $class?->cardImageUrl();
    $canUpload = \App\Models\ModuleAttachment::storageIsDurable();
@endphp
<div class="ui-card" data-class-background>
    <div class="ui-card-header">
        <h2 class="ui-card-title">Card background</h2>
        <p class="ui-card-subtitle">Shown at the top of the class card, for you and your students.</p>
    </div>
    <div class="ui-card-body space-y-4">
        <fieldset>
            <legend class="sr-only">Card background</legend>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <label class="class-background-option">
                    <input type="radio" name="card_background" value="auto" class="sr-only" @checked($background === 'auto')>
                    <span class="class-background-swatch class-background-swatch--icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </span>
                    <span class="class-background-name">Automatic</span>
                </label>

                @foreach (\App\Models\SchoolClass::CARD_PATTERNS as $pattern => $label)
                    <label class="class-background-option">
                        <input type="radio" name="card_background" value="{{ $pattern }}" class="sr-only" @checked($background === $pattern)>
                        <span class="class-background-swatch">
                            <x-class-pattern :pattern="$pattern" :id="'class-background-'.$pattern" />
                        </span>
                        <span class="class-background-name">{{ $label }}</span>
                    </label>
                @endforeach

                @if ($canUpload || $savedImage)
                    <label class="class-background-option">
                        <input type="radio" name="card_background" value="picture" class="sr-only" @checked($background === 'picture')>
                        <span class="class-background-swatch class-background-swatch--icon">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <img src="{{ $savedImage }}" alt="" data-class-background-preview data-saved-src="{{ $savedImage }}" @unless ($savedImage) hidden @endunless>
                        </span>
                        <span class="class-background-name">Your picture</span>
                    </label>
                @endif
            </div>
        </fieldset>
        @error('card_background') <p class="ui-error">{{ $message }}</p> @enderror

        @if ($canUpload)
            <div>
                <label for="card_image" class="ui-label">Upload a picture <span class="ui-optional">(optional)</span></label>
                <input type="file" name="card_image" id="card_image" accept="image/jpeg,image/png,image/webp"
                       class="ui-input" aria-describedby="card-image-hint card-image-error" @error('card_image') aria-invalid="true" @enderror>
                <p id="card-image-hint" class="ui-hint">JPG, PNG or WebP. Wide pictures fit best, and large ones are resized for you.</p>
                <p id="card-image-error" class="ui-error" data-class-background-error @unless ($errors->has('card_image')) hidden @endunless>{{ $errors->first('card_image') }}</p>
            </div>
        @else
            @error('card_image') <p class="ui-error">{{ $message }}</p> @enderror
        @endif
    </div>
</div>
