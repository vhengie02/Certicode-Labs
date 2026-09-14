<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Certicode Labs' }}</title>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #0f0f0f; -webkit-text-size-adjust: none; -ms-text-size-adjust: none;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #0f0f0f; margin: 0; padding: 48px 16px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
        <tr>
            <td align="center">
                <!-- Main Container Card (Supabase Surface Console Aesthetic) -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px; width: 100%; background-color: #171717; border: 1px solid #2e2e2e; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.75);">
                    
                    <!-- Header / Branding -->
                    <tr>
                        <td style="padding: 24px 28px; border-bottom: 1px solid #232323; background-color: #171717;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <!-- Logo Icon & Title -->
                                    <td style="vertical-align: middle;">
                                        <table cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="32" style="vertical-align: middle;">
                                                    <div style="height: 32px; width: 32px; border-radius: 6px; background-color: #141414; border: 1px solid #2e2e2e; text-align: center; line-height: 32px;">
                                                        <!-- Supabase Emerald Icon -->
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#3ecf8e" style="vertical-align: middle; display: inline-block;">
                                                            <path d="M21.362 9.354H12V.396a.396.396 0 0 0-.716-.233L.103 13.916a.396.396 0 0 0 .307.632H9.6v9.056a.396.396 0 0 0 .716.233l11.181-13.753a.396.396 0 0 0-.307-.632z"/>
                                                        </svg>
                                                    </div>
                                                </td>
                                                <td style="padding-left: 10px; vertical-align: middle;">
                                                    <span style="font-size: 16px; font-weight: 700; color: #ededed; letter-spacing: -0.4px;">
                                                        Certicode<span style="color: #3ecf8e;">Labs</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>

                                    <!-- Security Badge -->
                                    <td align="right" style="vertical-align: middle;">
                                        <div style="display: inline-block; padding: 4px 10px; border-radius: 9999px; background-color: #141414; border: 1px solid rgba(62, 207, 142, 0.3); font-family: 'Source Code Pro', 'Courier New', monospace; font-size: 10px; color: #3ecf8e; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background-color: #3ecf8e; margin-right: 4px; vertical-align: middle;"></span>
                                            Auth Console
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 36px 32px; background-color: #171717;">
                            <!-- Subject/Title -->
                            <h1 style="margin: 0 0 14px 0; font-size: 20px; font-weight: 700; color: #ededed; line-height: 1.3; letter-spacing: -0.3px;">
                                {{ $title ?? 'Google Authentication Code' }}
                            </h1>

                            <!-- Greeting -->
                            <p style="margin: 0 0 16px 0; font-size: 14px; color: #ededed; font-weight: 600;">
                                {{ $greeting ?? 'Hello developer,' }}
                            </p>

                            <!-- Message Lines -->
                            @isset($messageLines)
                                @foreach($messageLines as $line)
                                    <p style="margin: 0 0 16px 0; font-size: 14px; color: #a3a3a3; line-height: 1.65;">
                                        {!! $line !!}
                                    </p>
                                @endforeach
                            @endisset

                            <!-- Action Box: Verification Code (IDE Terminal Aesthetic) -->
                            @isset($code)
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 28px 0; background-color: #141414; border: 1px solid #2e2e2e; border-radius: 8px; overflow: hidden;">
                                    <!-- Terminal Titlebar -->
                                    <tr>
                                        <td style="padding: 10px 16px; border-bottom: 1px solid #232323; background-color: #111111;">
                                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td>
                                                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #ef4444; margin-right: 4px;"></span>
                                                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #eab308; margin-right: 4px;"></span>
                                                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #3ecf8e; margin-right: 8px;"></span>
                                                        <span style="font-family: 'Source Code Pro', 'Courier New', monospace; font-size: 11px; color: #666666;">verification_token.sh</span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <!-- Terminal Code Display -->
                                    <tr>
                                        <td style="padding: 28px 16px; text-align: center; background-color: #141414;">
                                            <div style="font-family: 'Source Code Pro', 'JetBrains Mono', 'Courier New', monospace; font-size: 38px; font-weight: 700; color: #3ecf8e; letter-spacing: 10px; padding: 14px 24px; background-color: #0f0f0f; border: 1px solid rgba(62, 207, 142, 0.35); border-radius: 8px; display: inline-block; text-shadow: 0 0 14px rgba(62, 207, 142, 0.25);">
                                                {{ $code }}
                                            </div>
                                            <p style="margin: 16px 0 0 0; font-family: 'Source Code Pro', 'Courier New', monospace; font-size: 11px; color: #737373;">
                                                Enter this 6-digit code in your active browser window to proceed.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            @endisset

                            <!-- Action Box: Pill CTA Button -->
                            @isset($actionUrl)
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 28px 0; text-align: center;">
                                    <tr>
                                        <td>
                                            <a href="{{ $actionUrl }}" target="_blank" style="background-color: #3ecf8e; border-radius: 9999px; color: #0f0f0f; display: inline-block; font-size: 13px; font-weight: 700; line-height: 1.5; padding: 11px 28px; text-decoration: none; text-align: center; font-family: 'Inter', sans-serif;">
                                                {{ $actionText ?? 'View Details' }} &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endisset

                            <!-- Closing Text -->
                            <p style="margin: 28px 0 0 0; font-size: 13px; color: #888888; line-height: 1.6;">
                                Best regards,<br>
                                <span style="color: #ededed; font-weight: 600;">Certicode<span style="color: #3ecf8e;">Labs</span> Platform</span>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer Section -->
                    <tr>
                        <td style="padding: 24px 32px; background-color: #141414; border-top: 1px solid #232323; text-align: center;">
                            <p style="margin: 0 0 6px 0; font-family: 'Source Code Pro', 'Courier New', monospace; font-size: 11px; color: #666666; line-height: 1.5;">
                                Automated telemetry authentication dispatch &bull; Do not reply to this email
                            </p>
                            <p style="margin: 0 0 14px 0; font-family: 'Source Code Pro', 'Courier New', monospace; font-size: 10px; color: #525252; line-height: 1.5;">
                                &copy; {{ date('Y') }} Certicode Labs. Built for secure & verified coding telemetry.
                            </p>
                            <div style="font-family: 'Source Code Pro', 'Courier New', monospace; font-size: 10px;">
                                <a href="{{ url('/') }}" style="color: #3ecf8e; text-decoration: none; margin: 0 8px;">Workspace</a>
                                <span style="color: #2e2e2e;">|</span>
                                <a href="{{ url('/settings') }}" style="color: #3ecf8e; text-decoration: none; margin: 0 8px;">Preferences</a>
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
