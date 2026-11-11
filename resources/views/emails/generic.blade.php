@php
    // Brand tokens from resources/css/brand.css, as solid hex: many mail clients drop rgba() and CSS variables.
    $ink = '#0b0c0b';
    $card = '#151815';
    $line = '#262926';
    $lineStrong = '#333633';
    $text = '#ececea';
    $textDim = '#a6a9a4';
    $textFaint = '#7d817b';
    $brand = '#3ecf8e';
    $onBrand = '#06150e';
    $sans = "'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    $mono = "'Geist Mono', ui-monospace, 'SF Mono', Menlo, Consolas, 'Courier New', monospace";

    $heading = $title ?? 'Certicode Labs';
    $preheader = \Illuminate\Support\Str::limit(strip_tags($messageLines[0] ?? ''), 110);

    // Mail clients strip inline SVG, so the mark is a PNG. Inside a real send it is attached to the
    // message, which also works when the app URL is not reachable from the reader's mail client.
    $logoSrc = isset($message) ? $message->embed(public_path('apple-touch-icon.png')) : asset('apple-touch-icon.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <title>{{ $heading }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@500;600&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: {{ $ink }}; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    @if($preheader !== '')
        <div style="display: none; max-height: 0; max-width: 0; overflow: hidden; opacity: 0; font-size: 1px; line-height: 1px; color: {{ $ink }};">{{ $preheader }}</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: {{ $ink }}; font-family: {{ $sans }};">
        <tr>
            <td align="center" style="padding: 40px 16px 48px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 520px; width: 100%;">

                    {{-- Wordmark, as on the sign-in pages --}}
                    <tr>
                        <td style="padding: 0 4px 24px 4px;">
                            <a href="{{ url('/') }}" target="_blank" style="text-decoration: none;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="vertical-align: middle; padding-right: 10px;">
                                            <img src="{{ $logoSrc }}" width="28" height="28" alt="" style="display: block; width: 28px; height: 28px; border: 0; border-radius: 6px;">
                                        </td>
                                        <td style="vertical-align: middle; font-family: {{ $sans }}; font-size: 17px; font-weight: 600; letter-spacing: -0.02em; color: {{ $text }};">
                                            Certicode <span style="font-weight: 500; color: {{ $textDim }};">Labs</span>
                                        </td>
                                    </tr>
                                </table>
                            </a>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color: {{ $card }}; border: 1px solid {{ $line }}; border-radius: 14px; padding: 36px 32px 32px 32px;">
                            <h1 style="margin: 0 0 20px 0; font-family: {{ $sans }}; font-size: 24px; line-height: 1.2; font-weight: 700; letter-spacing: -0.03em; color: {{ $text }};">
                                {{ $heading }}
                            </h1>

                            <p style="margin: 0 0 12px 0; font-size: 15px; line-height: 1.6; font-weight: 500; color: {{ $text }};">
                                {{ $greeting ?? 'Hello,' }}
                            </p>

                            @isset($messageLines)
                                @foreach($messageLines as $line_)
                                    <p style="margin: 0 0 12px 0; font-size: 15px; line-height: 1.65; color: {{ $textDim }};">
                                        {!! $line_ !!}
                                    </p>
                                @endforeach
                            @endisset

                            {{-- Verification code --}}
                            @isset($code)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 24px 0 0 0;">
                                    <tr>
                                        <td style="padding: 0 0 8px 0; font-size: 13px; font-weight: 500; color: {{ $textDim }};">
                                            Your verification code
                                        </td>
                                    </tr>
                                    <tr>
                                        <td align="center" style="background-color: {{ $ink }}; border: 1px solid {{ $lineStrong }}; border-radius: 10px; padding: 22px 12px;">
                                            {{-- Left padding balances the trailing letter-spacing so the digits sit centred --}}
                                            <span style="font-family: {{ $mono }}; font-size: 34px; line-height: 1; font-weight: 600; letter-spacing: 0.3em; padding-left: 0.3em; color: {{ $brand }};">{{ $code }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px 0 0 0; font-size: 13px; line-height: 1.55; color: {{ $textFaint }};">
                                            Enter it in the browser window where you asked for it.
                                        </td>
                                    </tr>
                                </table>
                            @endisset

                            {{-- Button --}}
                            @isset($actionUrl)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 24px 0 0 0;">
                                    <tr>
                                        <td style="background-color: {{ $brand }}; border-radius: 10px;">
                                            <a href="{{ $actionUrl }}" target="_blank" style="display: inline-block; padding: 14px 22px; font-family: {{ $sans }}; font-size: 15px; line-height: 1; font-weight: 600; color: {{ $onBrand }}; text-decoration: none; border-radius: 10px;">
                                                {{ $actionText ?? 'View details' }} &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin: 20px 0 0 0; font-size: 13px; line-height: 1.55; color: {{ $textFaint }};">
                                    If the button doesn't work, open this link:<br>
                                    <a href="{{ $actionUrl }}" target="_blank" style="color: {{ $brand }}; text-decoration: underline; word-break: break-all;">{{ $actionUrl }}</a>
                                </p>
                            @endisset

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 28px 0 0 0;">
                                <tr>
                                    <td style="border-top: 1px solid {{ $line }}; padding: 20px 0 0 0; font-size: 13px; line-height: 1.55; color: {{ $textFaint }};">
                                        {{ $footnote ?? "Didn't ask for this? You can ignore this email. Nothing changes on your account." }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 4px 0 4px; font-size: 12px; line-height: 1.6; color: {{ $textFaint }};">
                            This is an automated email, so replies aren't read.<br>
                            &copy; {{ date('Y') }} Certicode Labs
                            &nbsp;&middot;&nbsp; <a href="{{ url('/settings') }}" target="_blank" style="color: {{ $textDim }}; text-decoration: underline;">Settings</a>
                            &nbsp;&middot;&nbsp; <a href="{{ url('/privacy') }}" target="_blank" style="color: {{ $textDim }}; text-decoration: underline;">Privacy</a>
                            &nbsp;&middot;&nbsp; <a href="{{ url('/terms') }}" target="_blank" style="color: {{ $textDim }}; text-decoration: underline;">Terms</a>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
