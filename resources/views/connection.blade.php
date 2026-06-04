<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }} Connection Analytics</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|jetbrains-mono:400,700" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <style>
            :root {
                color-scheme: dark;
                --bg: #071018;
                --bg-strong: #0d1f2b;
                --panel: rgba(11, 26, 38, 0.9);
                --panel-soft: rgba(16, 37, 54, 0.85);
                --panel-border: rgba(133, 189, 211, 0.14);
                --text: #f3fbff;
                --muted: #98afbb;
                --line: rgba(152, 175, 187, 0.12);
                --primary: #33d1c6;
                --primary-soft: rgba(51, 209, 198, 0.14);
                --secondary: #f59e0b;
                --secondary-soft: rgba(245, 158, 11, 0.14);
                --danger: #ff6b6b;
                --danger-soft: rgba(255, 107, 107, 0.14);
                --success: #5ee08f;
                --success-soft: rgba(94, 224, 143, 0.14);
                --shadow: 0 36px 90px rgba(3, 9, 15, 0.45);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
                color: var(--text);
                background:
                    radial-gradient(circle at top left, rgba(51, 209, 198, 0.18), transparent 32%),
                    radial-gradient(circle at 85% 10%, rgba(245, 158, 11, 0.16), transparent 24%),
                    linear-gradient(155deg, var(--bg), var(--bg-strong));
            }

            .shell {
                width: min(100%, 1400px);
                margin: 0 auto;
                padding: 32px 24px 48px;
            }

            .topbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 14px;
                flex-wrap: wrap;
                margin-bottom: 18px;
            }

            .topbar-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .hero {
                display: grid;
                grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
                gap: 22px;
                margin-bottom: 22px;
            }

            .hero-panel,
            .panel {
                border: 1px solid var(--panel-border);
                border-radius: 26px;
                background: var(--panel);
                backdrop-filter: blur(18px);
                box-shadow: var(--shadow);
            }

            .hero-panel {
                padding: 30px;
            }

            .eyebrow {
                margin: 0 0 12px;
                color: var(--primary);
                font-weight: 700;
                font-size: 0.8rem;
                letter-spacing: 0.15em;
                text-transform: uppercase;
            }

            h1 {
                margin: 0;
                font-size: clamp(2.4rem, 6vw, 4.8rem);
                line-height: 0.95;
                letter-spacing: -0.05em;
                max-width: 10ch;
            }

            .lede {
                max-width: 58rem;
                margin: 16px 0 0;
                color: var(--muted);
                line-height: 1.7;
                font-size: 1.03rem;
            }

            .hero-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin-top: 22px;
            }

            .pill {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 14px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.04);
                border: 1px solid rgba(255, 255, 255, 0.06);
                color: var(--muted);
                font-size: 0.93rem;
            }

            .pill strong {
                color: var(--text);
            }

            .logout-form {
                margin: 0;
            }

            .logout-button {
                padding: 11px 16px;
                border: 0;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.06);
                color: var(--text);
                font: inherit;
                font-weight: 700;
                cursor: pointer;
            }

            .hero-spotlight {
                display: grid;
                gap: 14px;
            }

            .spotlight-card {
                padding: 24px;
                border-radius: 26px;
                border: 1px solid var(--panel-border);
                background:
                    linear-gradient(145deg, rgba(51, 209, 198, 0.18), transparent 65%),
                    var(--panel-soft);
            }

            .spotlight-card.secondary {
                background:
                    linear-gradient(145deg, rgba(245, 158, 11, 0.18), transparent 65%),
                    var(--panel-soft);
            }

            .spotlight-label,
            .section-label,
            .metric-label,
            .mini-label {
                display: block;
                color: var(--muted);
                font-size: 0.8rem;
                text-transform: uppercase;
                letter-spacing: 0.1em;
            }

            .spotlight-value {
                margin-top: 12px;
                font-size: clamp(2rem, 4vw, 3rem);
                font-weight: 700;
                letter-spacing: -0.04em;
            }

            .spotlight-copy {
                margin: 10px 0 0;
                color: var(--muted);
                line-height: 1.6;
            }

            .metrics {
                display: grid;
                grid-template-columns: repeat(6, minmax(0, 1fr));
                gap: 16px;
                margin-bottom: 22px;
            }

            .metric {
                padding: 20px;
                border-radius: 22px;
                border: 1px solid var(--panel-border);
                background: var(--panel);
            }

            .metric-value {
                margin-top: 12px;
                font-size: clamp(1.5rem, 3vw, 2.3rem);
                font-weight: 700;
                letter-spacing: -0.04em;
            }

            .metric-note {
                margin: 10px 0 0;
                color: var(--muted);
                line-height: 1.5;
                font-size: 0.92rem;
            }

            .metric.revenue {
                background:
                    linear-gradient(150deg, rgba(51, 209, 198, 0.12), transparent 70%),
                    var(--panel);
            }

            .metric.orders {
                background:
                    linear-gradient(150deg, rgba(245, 158, 11, 0.12), transparent 70%),
                    var(--panel);
            }

            .metric.users {
                background:
                    linear-gradient(150deg, rgba(94, 224, 143, 0.12), transparent 70%),
                    var(--panel);
            }

            .layout {
                display: grid;
                grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.9fr);
                gap: 22px;
            }

            .stack {
                display: grid;
                gap: 22px;
            }

            .panel {
                padding: 24px;
            }

            .panel-head {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 18px;
                margin-bottom: 18px;
            }

            .panel-head h2,
            .panel-head h3 {
                margin: 8px 0 0;
                font-size: 1.45rem;
                letter-spacing: -0.03em;
            }

            .panel-copy {
                margin: 0;
                color: var(--muted);
                line-height: 1.6;
            }

            .chart-wrap {
                height: 320px;
            }

            .progress-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
                margin-bottom: 18px;
            }

            .progress-card {
                padding: 18px;
                border-radius: 18px;
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 255, 255, 0.05);
            }

            .progress-value {
                margin-top: 10px;
                font-size: 1.8rem;
                font-weight: 700;
            }

            .bar {
                width: 100%;
                height: 12px;
                margin-top: 14px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.05);
                overflow: hidden;
            }

            .bar > span {
                display: block;
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, var(--primary), #8cf3eb);
            }

            .bar.secondary > span {
                background: linear-gradient(90deg, var(--secondary), #ffd58a);
            }

            .mini-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 14px;
            }

            .mini-card {
                padding: 16px;
                border-radius: 18px;
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 255, 255, 0.05);
            }

            .mini-value {
                margin-top: 8px;
                font-size: 1.3rem;
                font-weight: 700;
            }

            .roster,
            .table {
                width: 100%;
                border-collapse: collapse;
            }

            .roster td,
            .table td,
            .table th {
                padding: 12px 0;
                border-bottom: 1px solid var(--line);
                vertical-align: top;
            }

            .table th {
                color: var(--muted);
                text-transform: uppercase;
                letter-spacing: 0.08em;
                font-size: 0.78rem;
                text-align: left;
                font-weight: 600;
            }

            .mono {
                font-family: "JetBrains Mono", ui-monospace, monospace;
                font-size: 0.92rem;
            }

            .badge {
                display: inline-flex;
                align-items: center;
                padding: 6px 10px;
                border-radius: 999px;
                font-size: 0.78rem;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
            }

            .badge.success {
                background: var(--success-soft);
                color: var(--success);
            }

            .badge.warning {
                background: var(--secondary-soft);
                color: var(--secondary);
            }

            .badge.danger {
                background: var(--danger-soft);
                color: var(--danger);
            }

            .caption {
                color: var(--muted);
                font-size: 0.92rem;
            }

            .empty {
                padding: 16px 0 4px;
                color: var(--muted);
            }

            .footer-note {
                margin-top: 22px;
                color: var(--muted);
                font-size: 0.92rem;
                line-height: 1.6;
            }

            @media (max-width: 1200px) {
                .metrics {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            @media (max-width: 980px) {
                .hero,
                .layout {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 720px) {
                .shell {
                    padding-inline: 16px;
                }

                .metrics,
                .progress-grid,
                .mini-grid {
                    grid-template-columns: 1fr;
                }

                .panel,
                .hero-panel,
                .spotlight-card,
                .metric {
                    padding: 20px;
                }

                .table {
                    display: block;
                    overflow-x: auto;
                }
            }
        </style>
    </head>
    <body>
        @php
            $registrationProgressWidth = max(12, min(100, $highlights['registrations_previous_month'] > 0 ? ($highlights['registrations_this_month'] / $highlights['registrations_previous_month']) * 100 : ($highlights['registrations_this_month'] > 0 ? 100 : 12)));
            $activeRateWidth = max(8, min(100, $highlights['active_rate']));
        @endphp

        <div class="shell">
            <div class="topbar">
                <div class="topbar-actions">
                    <div class="pill">
                        <span>Admin</span>
                        <strong>{{ $adminUser->name ?: $adminUser->email }}</strong>
                    </div>
                    <div class="pill">
                        <span>Email</span>
                        <strong>{{ $adminUser->email }}</strong>
                    </div>
                </div>

                <form class="logout-form" method="POST" action="{{ route('connection.logout') }}">
                    @csrf
                    <button class="logout-button" type="submit">Log Out</button>
                </form>
            </div>

            <section class="hero">
                <div class="hero-panel">
                    <p class="eyebrow">Connection Admin Analytics</p>
                    <h1>Revenue. Users. Trends.</h1>
                    <p class="lede">Fast backend view of money, signups, orders, and admins.</p>

                    <div class="hero-meta">
                        <div class="pill">
                            <span>Window</span>
                            <strong>{{ $rangeLabel }}</strong>
                        </div>
                        <div class="pill">
                            <span>Route</span>
                            <strong class="mono">/connection</strong>
                        </div>
                        <div class="pill">
                            <span>Updated</span>
                            <strong>{{ $generatedAt->format('M j, Y g:i A') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="hero-spotlight">
                    <article class="spotlight-card">
                        <span class="spotlight-label">Total Revenue</span>
                        <div class="spotlight-value">TZS {{ number_format($highlights['total_revenue']) }}</div>
                        <p class="spotlight-copy">
                            {{ number_format($highlights['completed_orders']) }} completed orders.
                        </p>
                    </article>

                    <article class="spotlight-card secondary">
                        <span class="spotlight-label">Registration Growth</span>
                        <div class="spotlight-value">{{ $highlights['registration_growth'] >= 0 ? '+' : '' }}{{ number_format($highlights['registration_growth'], 1) }}%</div>
                        <p class="spotlight-copy">
                            {{ number_format($highlights['registrations_this_month']) }} this month, {{ number_format($highlights['registrations_previous_month']) }} last month.
                        </p>
                    </article>
                </div>
            </section>

            <section class="metrics">
                <article class="metric revenue">
                    <span class="metric-label">Revenue Today</span>
                    <div class="metric-value">TZS {{ number_format($highlights['today_revenue']) }}</div>
                    <p class="metric-note">Completed today.</p>
                </article>

                <article class="metric revenue">
                    <span class="metric-label">Revenue This Month</span>
                    <div class="metric-value">TZS {{ number_format($highlights['monthly_revenue']) }}</div>
                    <p class="metric-note">Month to date.</p>
                </article>

                <article class="metric orders">
                    <span class="metric-label">Completed Orders</span>
                    <div class="metric-value">{{ number_format($highlights['completed_orders']) }}</div>
                    <p class="metric-note">Paid and confirmed.</p>
                </article>

                <article class="metric orders">
                    <span class="metric-label">Pending Orders</span>
                    <div class="metric-value">{{ number_format($highlights['pending_orders']) }}</div>
                    <p class="metric-note">Still waiting.</p>
                </article>

                <article class="metric users">
                    <span class="metric-label">Total Registrations</span>
                    <div class="metric-value">{{ number_format($highlights['total_users']) }}</div>
                    <p class="metric-note">All accounts.</p>
                </article>

                <article class="metric users">
                    <span class="metric-label">Admin Accounts</span>
                    <div class="metric-value">{{ number_format($highlights['admins_count']) }}</div>
                    <p class="metric-note">Current admins.</p>
                </article>
            </section>

            <section class="layout">
                <div class="stack">
                    <section class="panel">
                        <div class="panel-head">
                            <div>
                                <span class="section-label">Revenue</span>
                                <h2>Revenue trend</h2>
                            </div>
                            <p class="panel-copy">30-day view.</p>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="revenueTrendChart"></canvas>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <div>
                                <span class="section-label">Registrations</span>
                                <h2>Registration trend</h2>
                            </div>
                            <p class="panel-copy">Daily signups.</p>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="registrationTrendChart"></canvas>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <div>
                                <span class="section-label">Transactions</span>
                                <h2>Latest activity</h2>
                            </div>
                            <p class="panel-copy">Newest orders.</p>
                        </div>

                        @if ($recentTransactions->isEmpty())
                            <p class="empty">No transactions yet.</p>
                        @else
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Buyer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Zone</th>
                                        <th>Recorded</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentTransactions as $transaction)
                                        @php
                                            $statusClass = $transaction->status === 'COMPLETED'
                                                ? 'success'
                                                : ($transaction->status === 'FAILED' ? 'danger' : 'warning');
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="mono">{{ $transaction->transaction_id ?: 'N/A' }}</div>
                                                <div class="caption">{{ $transaction->reference ?: 'No reference' }}</div>
                                            </td>
                                            <td>
                                                <div>{{ $transaction->buyer_name ?: ($transaction->user_email ?: 'Unknown') }}</div>
                                                <div class="caption">{{ $transaction->user_email ?: 'No email' }}</div>
                                            </td>
                                            <td>TZS {{ number_format((int) $transaction->amount) }}</td>
                                            <td><span class="badge {{ $statusClass }}">{{ $transaction->status }}</span></td>
                                            <td>{{ $transaction->zone === 'wakubwa' ? 'Wakubwa' : 'Connection' }}</td>
                                            <td>{{ optional($transaction->created_at)->format('M j, Y g:i A') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>

                    <section class="panel" id="gateway-panel">
                        <div class="panel-head">
                            <div>
                                <span class="section-label">Payments</span>
                                <h3>Payment Gateway</h3>
                            </div>
                            <p class="panel-copy">Switch between SonicPesa and Mobilipa.</p>
                        </div>

                        <form id="gateway-form" method="POST" action="{{ route('connection.gateway') }}" style="display:none;">
                            @csrf
                            <input type="hidden" name="provider" id="gateway-provider" value="{{ $activeGateway }}" />
                        </form>

                        <div class="gateway-toggles" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                            <button
                                type="button"
                                class="gateway-btn {{ $activeGateway === 'sonicpesa' ? 'active' : '' }}"
                                data-provider="sonicpesa"
                                onclick="submitGateway(this)"
                            >
                                <span style="font-size:1.1rem;">🚀</span> SonicPesa
                            </button>
                            <button
                                type="button"
                                class="gateway-btn {{ $activeGateway === 'mobilipa' ? 'active' : '' }}"
                                data-provider="mobilipa"
                                onclick="submitGateway(this)"
                            >
                                <span style="font-size:1.1rem;">💸</span> Mobilipa
                            </button>
                        </div>

                        @if (session('status'))
                            <p class="caption" id="gateway-status" style="color:var(--success);font-size:0.9rem;">{{ session('status') }}</p>
                        @else
                            <p class="caption" id="gateway-status" style="color:var(--muted);font-size:0.9rem;">
                                Active: <strong style="color:var(--primary);text-transform:capitalize;">{{ $activeGateway }}</strong>
                            </p>
                        @endif
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <div>
                                <span class="section-label">Admins</span>
                                <h3>Existing admin roster</h3>
                            </div>
                            <p class="panel-copy">Current backend admins.</p>
                        </div>

                        @if ($admins->isEmpty())
                            <p class="empty">No admins found.</p>
                        @else
                            <table class="roster">
                                <tbody>
                                    @foreach ($admins as $admin)
                                        <tr>
                                            <td>
                                                <div>{{ $admin->name ?: $admin->email }}</div>
                                                <div class="caption">{{ $admin->email }}</div>
                                            </td>
                                            <td>
                                                <span class="badge {{ $admin->status === 'banned' ? 'danger' : 'success' }}">
                                                    {{ strtoupper($admin->status ?: 'active') }}
                                                </span>
                                            </td>
                                            <td class="caption">
                                                Joined {{ optional($admin->created_at)->format('M j, Y') }}
                                                <br>
                                                Last login {{ optional($admin->last_login)->format('M j, Y g:i A') ?: 'not recorded' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>
                </div>
            </section>

            <p class="footer-note">
                Revenue uses <strong>COMPLETED</strong> transactions only.
            </p>
        </div>

        <script>
            const chartData = @json($chartData);
            const currencyFormatter = new Intl.NumberFormat('en-US');

            Chart.defaults.color = '#d9eef5';
            Chart.defaults.font.family = '"Instrument Sans", ui-sans-serif, system-ui, sans-serif';
            Chart.defaults.borderColor = 'rgba(152, 175, 187, 0.12)';

            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            color: '#98afbb',
                        },
                    },
                },
            };

            new Chart(document.getElementById('revenueTrendChart'), {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Revenue (TZS)',
                        data: chartData.revenue,
                        tension: 0.35,
                        fill: true,
                        borderWidth: 3,
                        borderColor: '#33d1c6',
                        backgroundColor: 'rgba(51, 209, 198, 0.16)',
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#8cf3eb',
                    }],
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return `Revenue: TZS ${currencyFormatter.format(context.parsed.y)}`;
                                },
                            },
                        },
                    },
                    scales: {
                        ...commonOptions.scales,
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#98afbb',
                                callback(value) {
                                    return `TZS ${currencyFormatter.format(value)}`;
                                },
                            },
                        },
                    },
                },
            });

            new Chart(document.getElementById('registrationTrendChart'), {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'New registrations',
                        data: chartData.registrations,
                        borderRadius: 8,
                        backgroundColor: 'rgba(245, 158, 11, 0.68)',
                        hoverBackgroundColor: '#fbbf24',
                    }],
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return `Registrations: ${currencyFormatter.format(context.parsed.y)}`;
                                },
                            },
                        },
                    },
                    scales: {
                        ...commonOptions.scales,
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                color: '#98afbb',
                            },
                        },
                    },
                },
            });

            new Chart(document.getElementById('zoneRevenueChart'), {
                type: 'doughnut',
                data: {
                    labels: chartData.zoneLabels,
                    datasets: [{
                        label: 'Zone revenue',
                        data: chartData.zoneRevenue,
                        backgroundColor: [
                            'rgba(51, 209, 198, 0.85)',
                            'rgba(245, 158, 11, 0.85)',
                        ],
                        borderColor: [
                            '#33d1c6',
                            '#f59e0b',
                        ],
                        borderWidth: 1,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#d9eef5',
                                usePointStyle: true,
                                boxWidth: 10,
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return `${context.label}: TZS ${currencyFormatter.format(context.parsed)}`;
                                },
                            },
                        },
                    },
                },
            });
        </script>

        <style>
            .gateway-btn {
                flex: 1 1 120px;
                padding: 14px 18px;
                border-radius: 16px;
                border: 1px solid var(--panel-border);
                background: rgba(255, 255, 255, 0.03);
                color: var(--muted);
                font: inherit;
                font-weight: 600;
                font-size: 0.95rem;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: all 0.2s ease;
            }

            .gateway-btn:hover {
                background: rgba(255, 255, 255, 0.06);
                border-color: rgba(133, 189, 211, 0.3);
            }

            .gateway-btn.active {
                border-color: var(--primary);
                background:
                    linear-gradient(145deg, rgba(51, 209, 198, 0.18), transparent 65%),
                    var(--panel-soft);
                color: var(--text);
            }

            .gateway-btn.active[data-provider="mobilipa"] {
                border-color: var(--secondary);
                background:
                    linear-gradient(145deg, rgba(245, 158, 11, 0.18), transparent 65%),
                    var(--panel-soft);
            }
        </style>

        <script>
            function submitGateway(button) {
                const provider = button.dataset.provider;
                if (button.classList.contains('active')) return;

                document.getElementById('gateway-provider').value = provider;
                document.getElementById('gateway-form').submit();
            }
        </script>
    </body>
</html>
