<nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-5 gap-y-1']) }} aria-label="Legal">
    <a href="{{ route('legal.privacy') }}" class="hover:text-white transition-colors">Privacy</a>
    <a href="{{ route('legal.terms') }}" class="hover:text-white transition-colors">Terms</a>
    <a href="{{ route('legal.cookies') }}" class="hover:text-white transition-colors">Cookies</a>
</nav>
