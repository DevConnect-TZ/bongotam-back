<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }} API Status</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|jetbrains-mono:400,700" rel="stylesheet" />

        <style>
            :root {
                color-scheme: dark;
                --bg: #08131f;
                --bg-accent: #10253b;
                --panel: rgba(8, 19, 31, 0.82);
                --panel-border: rgba(125, 211, 252, 0.16);
                --text: #ecfeff;
                --muted: #9fb7c6;
                --success: #58f3a2;
                --highlight: #7dd3fc;
                --shadow: 0 30px 80px rgba(2, 8, 23, 0.45);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(88, 243, 162, 0.18), transparent 32%),
                    radial-gradient(circle at 85% 15%, rgba(125, 211, 252, 0.18), transparent 28%),
                    linear-gradient(145deg, var(--bg), var(--bg-accent));
                color: var(--text);
            }

            main {
                position: relative;
                display: grid;
                place-items: center;
                min-height: 100vh;
                padding: 24px;
                overflow: hidden;
            }

            .orb {
                position: absolute;
                width: 18rem;
                height: 18rem;
                border-radius: 999px;
                filter: blur(24px);
                opacity: 0.35;
            }

            .orb.one {
                top: 4rem;
                left: -4rem;
                background: rgba(88, 243, 162, 0.28);
            }

            .orb.two {
                right: -5rem;
                bottom: 3rem;
                background: rgba(125, 211, 252, 0.24);
            }

            .panel {
                position: relative;
                z-index: 1;
                width: min(100%, 980px);
                padding: 32px;
                border: 1px solid var(--panel-border);
                border-radius: 28px;
                background: var(--panel);
                backdrop-filter: blur(16px);
                box-shadow: var(--shadow);
            }

            .eyebrow {
                margin: 0 0 12px;
                color: var(--highlight);
                font-size: 0.82rem;
                font-weight: 700;
                letter-spacing: 0.16em;
                text-transform: uppercase;
            }

            h1 {
                margin: 0;
                max-width: 12ch;
                font-size: clamp(2.4rem, 8vw, 5.8rem);
                line-height: 0.94;
                letter-spacing: -0.04em;
            }

            .lede {
                max-width: 52rem;
                margin: 18px 0 0;
                color: var(--muted);
                font-size: 1.05rem;
                line-height: 1.7;
            }

            .stats {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 18px;
                margin-top: 32px;
            }

            .card {
                padding: 22px;
                border: 1px solid rgba(159, 183, 198, 0.14);
                border-radius: 22px;
                background: rgba(11, 24, 39, 0.72);
            }

            .card.primary {
                background:
                    linear-gradient(150deg, rgba(88, 243, 162, 0.18), transparent 70%),
                    rgba(11, 24, 39, 0.84);
                border-color: rgba(88, 243, 162, 0.24);
            }

            .label {
                display: block;
                margin-bottom: 12px;
                color: var(--muted);
                font-size: 0.82rem;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .value {
                font-size: clamp(1.7rem, 4vw, 2.5rem);
                font-weight: 700;
                line-height: 1.1;
                letter-spacing: -0.03em;
            }

            .mono {
                font-family: "JetBrains Mono", ui-monospace, monospace;
                font-size: 1rem;
                letter-spacing: normal;
            }

            .meta {
                margin: 10px 0 0;
                color: var(--muted);
                font-size: 0.95rem;
                line-height: 1.6;
            }

            .status-pill {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 10px 14px;
                border-radius: 999px;
                background: rgba(88, 243, 162, 0.12);
                color: var(--success);
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .status-pill::before {
                content: "";
                width: 0.72rem;
                height: 0.72rem;
                border-radius: 999px;
                background: currentColor;
                box-shadow: 0 0 0 0 rgba(88, 243, 162, 0.45);
                animation: pulse 1.9s infinite;
            }

            .footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 18px;
                margin-top: 28px;
                padding-top: 24px;
                border-top: 1px solid rgba(159, 183, 198, 0.12);
            }

            .footer p {
                margin: 0;
                color: var(--muted);
                line-height: 1.6;
            }

            @keyframes pulse {
                0% {
                    box-shadow: 0 0 0 0 rgba(88, 243, 162, 0.45);
                }

                70% {
                    box-shadow: 0 0 0 14px rgba(88, 243, 162, 0);
                }

                100% {
                    box-shadow: 0 0 0 0 rgba(88, 243, 162, 0);
                }
            }

            @media (max-width: 860px) {
                .stats {
                    grid-template-columns: 1fr;
                }

                .footer {
                    flex-direction: column;
                    align-items: flex-start;
                }
            }

            @media (max-width: 640px) {
                .panel {
                    padding: 24px;
                }

                .lede,
                .meta,
                .footer p {
                    font-size: 0.95rem;
                }
            }
        </style>
    </head>
    <body>
        <main>
            <div class="orb one"></div>
            <div class="orb two"></div>

            <section class="panel">
                <p class="eyebrow">Backend Status Board</p>
                <h1 id="message">{{ $status['message'] }}</h1>
                <p class="lede">
                   API uptime.
                </p>

                <div class="stats">
                    <article class="card primary">
                        <span class="label">API Uptime</span>
                        <div class="value" id="uptime">{{ $status['uptime_human'] }}</div>
                        <p class="meta">
                            Started <time id="started-at" datetime="{{ $status['started_at'] }}">{{ $status['started_at_human'] }}</time>
                        </p>
                    </article>

                    <article class="card">
                        <span class="label">Current Status</span>
                        <div class="status-pill" id="status-label">{{ $status['status_label'] }}</div>
                        <p class="meta">
                            Last checked <time id="checked-at" datetime="{{ $status['checked_at'] }}">{{ $status['checked_at_human'] }}</time>
                        </p>
                    </article>

                    <article class="card">
                        <span class="label">Live Endpoint</span>
                        <div class="value mono">/api/uptime</div>
                        <p class="meta">
                            The connectionMpya Api
                        </p>
                    </article>
                </div>

                <div class="footer">
                    <p id="summary">Hoora! The API is up and responding normally.</p>
                    <p>Health probe: <span class="mono">/up</span></p>
                </div>
            </section>
        </main>

        <script>
            const uptimeEndpoint = @json(route('api.uptime'));

            async function refreshStatusBoard() {
                try {
                    const response = await fetch(uptimeEndpoint, {
                        headers: {
                            Accept: 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Unable to refresh API status.');
                    }

                    const payload = await response.json();

                    document.getElementById('message').textContent = payload.message;
                    document.getElementById('uptime').textContent = payload.uptime_human;
                    document.getElementById('status-label').textContent = payload.status_label;
                    document.getElementById('summary').textContent = `${payload.message} Uptime: ${payload.uptime_human}.`;

                    const startedAt = document.getElementById('started-at');
                    startedAt.dateTime = payload.started_at;
                    startedAt.textContent = payload.started_at_human;

                    const checkedAt = document.getElementById('checked-at');
                    checkedAt.dateTime = payload.checked_at;
                    checkedAt.textContent = payload.checked_at_human;
                } catch (error) {
                    console.error(error);
                }
            }

            window.setInterval(refreshStatusBoard, 30000);
        </script>
    </body>
</html>
