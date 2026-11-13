{{-- Plain-text part of emails.generic. Output is raw on purpose: this part is not HTML, so escaping
     would print entities such as &amp; inside the reset link. Message lines arrive as HTML (alerts
     escape them), so they are turned back into plain text here. --}}
{!! $title ?? 'Certicode Labs' !!}

{!! $greeting ?? 'Hello,' !!}

@foreach(($messageLines ?? []) as $line_)
{!! html_entity_decode(strip_tags($line_), ENT_QUOTES) !!}

@endforeach
@isset($code)
Your verification code: {!! $code !!}
Enter it in the browser window where you asked for it.

@endisset
@isset($actionUrl)
{!! $actionText ?? 'View details' !!}:
{!! $actionUrl !!}

@endisset
{!! $footnote ?? "Didn't ask for this? You can ignore this email. Nothing changes on your account." !!}

This is an automated email, so replies aren't read.
Certicode Labs: {!! url('/') !!}
