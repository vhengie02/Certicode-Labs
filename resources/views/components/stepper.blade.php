{{--
    Steps shown one at a time, with a row of indicators and Back and Continue buttons. Blade + Alpine
    port of React Bits' Stepper.

    steps: [['title' => 'Step', 'body' => 'What to do', 'done' => false, 'action' => 'Link text', 'href' => '/url', 'download' => false]]
    initial: the step shown first, counted from 1
--}}
@props(['steps', 'initial' => 1])
<div {{ $attributes->merge(['class' => 'stepper']) }} x-data="stepper({ initial: {{ $initial }}, total: {{ count($steps) }} })">
    <div class="stepper__track" x-ref="track">
        <span class="stepper__glider" x-ref="glider" aria-hidden="true"></span>
        @foreach ($steps as $i => $step)
            @php
                $number = $i + 1;
                $status = $step['done'] ? 'complete' : ($number === $initial ? 'active' : 'upcoming');
            @endphp
            <button type="button" class="stepper__segment"
                    aria-label="Step {{ $number }}: {{ $step['title'] }}{{ $step['done'] ? ' (done)' : '' }}"
                    @if ($number === $initial) aria-current="step" @endif
                    x-bind:aria-current="current === {{ $number }} ? 'step' : null"
                    x-on:click="go({{ $number }})">
                <span class="stepper__badge" data-status="{{ $status }}"
                      x-bind:data-status="status({{ $number }}, {{ $step['done'] ? 'true' : 'false' }})">
                    @if ($step['done'])
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span class="stepper__title">{{ $step['title'] }}</span>
            </button>
        @endforeach
    </div>
    <div class="stepper__body">
        <div class="stepper__viewport" x-ref="viewport" aria-live="polite">
            @foreach ($steps as $i => $step)
                <div class="stepper__pane" tabindex="-1" @if ($i + 1 !== $initial) hidden @endif>
                    <h3 class="text-base font-semibold text-[#ededed]">{{ $step['title'] }}</h3>
                    <p class="mt-1.5 max-w-[60ch] text-sm leading-relaxed text-[#a3a3a3]">{{ $step['body'] }}</p>
                    @if ($step['done'])
                        <p class="mt-3 text-sm font-medium text-[#3ecf8e]">Done</p>
                    @else
                        <a href="{{ $step['href'] }}" @if (is_string($step['download'] ?? null)) download="{{ $step['download'] }}" @elseif (!empty($step['download'])) download @endif
                           class="mt-3 inline-block text-sm font-semibold text-[#3ecf8e] hover:underline underline-offset-2">{{ $step['action'] }} &rarr;</a>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="stepper__footer">
            <button type="button" class="stepper__button stepper__button--back" @if ($initial <= 1) hidden @endif
                    x-bind:hidden="current <= 1" x-on:click="go(current - 1)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                <span>Back</span>
            </button>
            <button type="button" class="stepper__button stepper__button--next" @if ($initial >= count($steps)) hidden @endif
                    x-bind:hidden="current >= total" x-on:click="go(current + 1)">
                <span>Continue</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</div>
