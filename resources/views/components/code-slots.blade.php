@props(['length' => 6, 'label' => 'One-time code'])

{{-- The slot row for a one-time code. Put it inside a form that carries x-data="codeSlots({ url })"
     from resources/js/code-slots.js. Without that script it is a plain field named "code". --}}
<div {{ $attributes->class('code-slots') }}>
    <div class="code-slots__row" x-ref="row"
         :data-status="status" :data-disabled="sending" :style="`--cs-active: ${active}`"
         @mousedown="point($event)">
        <input x-ref="input" class="code-slots__input ui-input ui-input-mono" type="text" name="code" required
               inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="{{ $length }}"
               aria-label="{{ $label }}" :aria-invalid="status === 'error'" :readonly="status === 'success'"
               @keydown="key($event)" @paste.prevent="paste($event)" @input="typed($event)"
               @focus="focused = true" @blur="focused = false">
        @for ($i = 0; $i < $length; $i++)
            <span class="code-slots__slot" aria-hidden="true"
                  :data-active="focused && !locked && active === {{ $i }}" :data-filled="Boolean(slots[{{ $i }}])"
                  :style="`--cs-delay: ${delays[{{ $i }}]}ms; --cs-index: {{ $i }}`">
                <span class="code-slots__fill"></span>
                <span class="code-slots__digit" x-text="shown[{{ $i }}]"></span>
            </span>
        @endfor
        <span class="code-slots__wash" aria-hidden="true">
            <span class="code-slots__check">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </span>
        </span>
        <span class="code-slots__caret" aria-hidden="true" :data-show="caretShown">
            <span class="code-slots__caret-line"></span>
        </span>
    </div>
    <span class="sr-only" aria-live="polite" x-text="progress"></span>
</div>
