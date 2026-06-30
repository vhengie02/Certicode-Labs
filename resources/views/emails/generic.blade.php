<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Certicode Labs Alert' }}</title>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #0d1117; -webkit-text-size-adjust: none; -ms-text-size-adjust: none;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #0d1117; margin: 0; padding: 40px 20px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table width="100%" max-width="580" cellpadding="0" cellspacing="0" border="0" style="max-width: 580px; width: 100%; background-color: #161b22; border: 1px solid #30363d; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);">
                    
                    <!-- Header / Branding -->
                    <tr>
                        <td style="padding: 32px 32px 24px 32px; border-bottom: 1px solid #30363d; background-color: #161b22;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="36" style="vertical-align: middle;">
                                        <!-- Logo Icon Shield -->
                                        <div style="height: 36px; width: 36px; border-radius: 8px; background-color: #1F6FEB; display: block; text-align: center;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top: 8px; display: inline-block;">
                                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                            </svg>
                                        </div>
                                    </td>
                                    <td style="padding-left: 12px; vertical-align: middle;">
                                        <span style="font-size: 18px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">Certicode Labs</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 40px 32px; background-color: #161b22;">
                            <!-- Subject/Title -->
                            <h1 style="margin: 0 0 16px 0; font-size: 22px; font-weight: 700; color: #ffffff; line-height: 1.3;">
                                {{ $title ?? 'Notification Alert' }}
                            </h1>

                            <!-- Greeting -->
                            <p style="margin: 0 0 20px 0; font-size: 16px; color: #e6edf3; font-weight: 500;">
                                {{ $greeting ?? 'Hello,' }}
                            </p>

                            <!-- Message Lines -->
                            @isset($messageLines)
                                @foreach($messageLines as $line)
                                    <p style="margin: 0 0 20px 0; font-size: 15px; color: #c9d1d9; line-height: 1.6;">
                                        {!! $line !!}
                                    </p>
                                @endforeach
                            @endisset

                            <!-- Action Box: Verification Code (Monospace Editor UI) -->
                            @isset($code)
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 32px 0; background-color: #0d1117; border: 1px solid #30363d; border-radius: 8px;">
                                    <tr>
                                        <td style="padding: 24px; text-align: center;">
                                            <!-- Code Header decoration mimicking terminal bar -->
                                            <div style="text-align: left; margin-bottom: 16px; opacity: 0.7;">
                                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #ff5f56; margin-right: 4px;"></span>
                                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #ffbd2e; margin-right: 4px;"></span>
                                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #27c93f; margin-right: 8px;"></span>
                                                <span style="font-family: 'Courier New', monospace; font-size: 11px; color: #8b949e; vertical-align: middle;">verification_token.sh</span>
                                            </div>
                                            <!-- The Code -->
                                            <div style="font-family: 'JetBrains Mono', 'Courier New', monospace; font-size: 36px; font-weight: 700; color: #58A6FF; letter-spacing: 8px; padding: 12px; background-color: #161b22; border: 1px solid #21262d; border-radius: 6px; display: inline-block; text-shadow: 0 0 10px rgba(88, 166, 255, 0.15);">
                                                {{ $code }}
                                            </div>
                                            <!-- Instructions -->
                                            <p style="margin: 16px 0 0 0; font-size: 13px; color: #8b949e;">
                                                Please enter this verification code in the window to proceed.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            @endisset

                            <!-- Action Box: CTA Button -->
                            @isset($actionUrl)
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 32px 0; text-align: center;">
                                    <tr>
                                        <td>
                                            <a href="{{ $actionUrl }}" target="_blank" style="background-color: #238636; border: 1px solid #2ea043; border-radius: 6px; color: #ffffff; display: inline-block; font-size: 15px; font-weight: 600; line-height: 1.5; padding: 12px 28px; text-decoration: none; text-align: center; box-shadow: 0 4px 12px rgba(35, 134, 54, 0.25);">
                                                {{ $actionText ?? 'View Details' }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endisset

                            <!-- Closing Text -->
                            <p style="margin: 24px 0 0 0; font-size: 15px; color: #c9d1d9; line-height: 1.5;">
                                Best regards,<br>
                                <strong style="color: #ffffff;">Certicode Labs Team</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer Section -->
                    <tr>
                        <td style="padding: 32px; background-color: #0d1117; border-top: 1px solid #30363d; text-align: center;">
                            <p style="margin: 0 0 8px 0; font-size: 12px; color: #8b949e; line-height: 1.5;">
                                You received this email because you are registered at Certicode Labs.
                            </p>
                            <p style="margin: 0 0 16px 0; font-size: 12px; color: #8b949e; line-height: 1.5;">
                                &copy; 2026 Certicode Labs. All rights reserved. Built for secure & verified coding telemetry.
                            </p>
                            <div style="font-size: 11px;">
                                <a href="{{ url('/') }}" style="color: #58A6FF; text-decoration: none; margin: 0 8px;">Website</a>
                                <span style="color: #30363d;">|</span>
                                <a href="{{ url('/settings') }}" style="color: #58A6FF; text-decoration: none; margin: 0 8px;">Preferences</a>
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
