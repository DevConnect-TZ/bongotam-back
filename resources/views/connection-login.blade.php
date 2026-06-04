<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }} Admin Access</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        <style>
            :root {
                color-scheme: dark;
                --bg: #08111a;
                --panel: rgba(13, 26, 37, 0.9);
                --panel-border: rgba(105, 174, 208, 0.14);
                --text: #f5fbff;
                --muted: #9db0bc;
                --primary: #33d1c6;
                --danger: #ff7d7d;
                --input: rgba(255, 255, 255, 0.06);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 24px;
                font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
                color: var(--text);
                background:
                    radial-gradient(circle at top left, rgba(51, 209, 198, 0.18), transparent 34%),
                    linear-gradient(150deg, #071018, var(--bg));
            }

            .panel {
                width: min(100%, 560px);
                padding: 32px;
                border: 1px solid var(--panel-border);
                border-radius: 24px;
                background: var(--panel);
                backdrop-filter: blur(16px);
            }

            .eyebrow {
                margin: 0 0 12px;
                color: var(--primary);
                text-transform: uppercase;
                letter-spacing: 0.14em;
                font-size: 0.8rem;
                font-weight: 700;
            }

            h1 {
                margin: 0 0 12px;
                font-size: clamp(2rem, 5vw, 3.2rem);
                line-height: 0.96;
                letter-spacing: -0.05em;
            }

            p {
                margin: 0;
                color: var(--muted);
                line-height: 1.65;
            }

            .error {
                margin-top: 18px;
                padding: 12px 14px;
                border-radius: 14px;
                background: rgba(255, 125, 125, 0.12);
                border: 1px solid rgba(255, 125, 125, 0.2);
                color: var(--danger);
            }

            .status {
                margin-top: 18px;
                padding: 12px 14px;
                border-radius: 14px;
                background: rgba(51, 209, 198, 0.12);
                border: 1px solid rgba(51, 209, 198, 0.2);
                color: var(--primary);
            }

            .form {
                display: grid;
                gap: 14px;
                margin-top: 24px;
            }

            .field {
                display: grid;
                gap: 8px;
            }

            .field span {
                font-size: 0.95rem;
                color: var(--text);
                font-weight: 600;
            }

            .field input {
                width: 100%;
                padding: 14px 16px;
                border-radius: 14px;
                border: 1px solid rgba(255, 255, 255, 0.08);
                background: var(--input);
                color: var(--text);
                font: inherit;
            }

            .field input:focus {
                outline: 2px solid rgba(51, 209, 198, 0.45);
                outline-offset: 1px;
            }

            .actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 18px;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 180px;
                padding: 13px 16px;
                border-radius: 999px;
                border: none;
                font-weight: 700;
                font: inherit;
                cursor: pointer;
                text-decoration: none;
            }

            .button.primary {
                background: var(--primary);
                color: #06201f;
            }

            .button.ghost {
                border: 1px solid rgba(255, 255, 255, 0.08);
                color: var(--text);
                background: rgba(255, 255, 255, 0.03);
            }

            .subtle {
                margin-top: 18px;
                font-size: 0.95rem;
            }
        </style>
    </head>
    <body>
        <section class="panel">
            <p class="eyebrow">Admin Only</p>
            @if ($awaitingOtp)
                <h1>Enter your code.</h1>
                <p>We sent a 6-digit code to {{ $challengeEmail }}. It expires in {{ $challengeExpiresIn ?? 10 }} minute(s).</p>
            @else
                <h1>Sign in to analytics.</h1>
                <p>Enter your admin email and we will send a login code.</p>
            @endif

            @if ($error)
                <div class="error">{{ $error }}</div>
            @endif

            @if ($status)
                <div class="status">{{ $status }}</div>
            @endif

            @if ($awaitingOtp)
                <form class="form" method="POST" action="{{ route('connection.verify') }}">
                    @csrf

                    <label class="field">
                        <span>OTP code</span>
                        <input
                            type="text"
                            name="otp"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            required
                        >
                    </label>

                    <div class="actions">
                        <button class="button primary" type="submit">Verify Code</button>
                    </div>
                </form>

                <div class="actions">
                    <form method="POST" action="{{ route('connection.authenticate') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ data_get(session('connection_login_otp'), 'email') }}">
                        <button class="button ghost" type="submit">Resend Code</button>
                    </form>

                    <form method="POST" action="{{ route('connection.reset') }}">
                        @csrf
                        <button class="button ghost" type="submit">Use Another Email</button>
                    </form>
                </div>
            @else
                <form class="form" method="POST" action="{{ route('connection.authenticate') }}">
                    @csrf

                    <label class="field">
                        <span>Email</span>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            required
                        >
                    </label>

                    <div class="actions">
                        <button class="button primary" type="submit">Send Code</button>
                    </div>
                </form>
            @endif

            <p class="subtle">Frontend access can still open analytics too.</p>

            <div class="actions">
                <a class="button ghost" href="{{ $frontendUrl }}/login">Frontend Login</a>
                <a class="button ghost" href="{{ $frontendUrl }}/admin">Open Admin Page</a>
            </div>
        </section>
    </body>
</html>
