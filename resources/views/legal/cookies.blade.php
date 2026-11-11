@extends('layouts.public')

@section('title', 'Cookie Notice')
@section('robots', 'index, follow')
@section('headerLabel', 'Legal')

@include('legal._styles')

@section('content')
<article class="legal">
    <h1>Cookie Notice</h1>
    <p class="legal-meta">Effective {{ \Illuminate\Support\Carbon::parse(config('legal.effective_date'))->format('F j, Y') }}</p>

    <p>Certicode only uses cookies it needs to work. We don't use advertising or analytics cookies, so there is nothing to opt out of and no cookie banner. Blocking these cookies will stop you from signing in.</p>

    <h2 id="cookies">Cookies</h2>
    <div class="legal-table">
        <table>
            <thead><tr><th>Name</th><th>What it does</th><th>Lasts</th></tr></thead>
            <tbody>
                <tr><td><code>{{ config('session.cookie') }}</code></td><td>Keeps you signed in during a visit</td><td>{{ config('session.lifetime') }} minutes after your last activity</td></tr>
                <tr><td><code>XSRF-TOKEN</code></td><td>Protects forms against forged requests</td><td>{{ config('session.lifetime') }} minutes</td></tr>
                <tr><td><code>remember_web_…</code></td><td>Keeps you signed in, only if you tick "Keep me signed in"</td><td>Up to 400 days, or until you sign out</td></tr>
            </tbody>
        </table>
    </div>

    <h2 id="storage">Browser storage</h2>
    <p>We also save small settings in your browser, such as your theme choice and how long until you can request another email code. These stay on your device and aren't used to track you.</p>

    <h2 id="third-party">Google Fonts</h2>
    <p>Our pages load fonts from Google, so your browser sends Google its IP address when it fetches them. See the <a href="{{ route('legal.privacy') }}#sharing">Privacy Policy</a> for all our service providers.</p>
</article>
@endsection
