<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConnectionDashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $admin = ConnectionAccessController::authenticatedAdmin($request);

        if (! $admin) {
            return redirect()->route('connection.login');
        }

        $today = CarbonImmutable::now();
        $trendStart = $today->subDays(29)->startOfDay();
        $trendEnd = $today->endOfDay();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();
        $previousMonthStart = $today->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $today->subMonthNoOverflow()->endOfMonth();

        $completedTransactions = Transaction::query()->where('status', 'COMPLETED');
        $allTransactions = Transaction::query();
        $allUsers = User::query();

        $totalRevenue = (int) (clone $completedTransactions)->sum('amount');
        $monthlyRevenue = (int) (clone $completedTransactions)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('amount');
        $todayRevenue = (int) (clone $completedTransactions)
            ->whereDate('created_at', $today->toDateString())
            ->sum('amount');
        $completedOrders = (int) (clone $completedTransactions)->count();
        $pendingOrders = (int) (clone $allTransactions)->where('status', 'PENDING')->count();
        $failedOrders = (int) (clone $allTransactions)->where('status', 'FAILED')->count();

        $totalUsers = (int) (clone $allUsers)->count();
        $adminsCount = (int) (clone $allUsers)->where('role', 'admin')->count();
        $activeUsers = (int) (clone $allUsers)->where('status', 'active')->count();
        $bannedUsers = (int) (clone $allUsers)->where('status', 'banned')->count();
        $registrationsThisMonth = (int) (clone $allUsers)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();
        $registrationsPreviousMonth = (int) (clone $allUsers)
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $activeRate = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0.0;
        $registrationGrowth = $this->growthPercentage($registrationsThisMonth, $registrationsPreviousMonth);

        $revenueByDay = Transaction::query()
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(amount), 0) as total')
            ->where('status', 'COMPLETED')
            ->whereBetween('created_at', [$trendStart, $trendEnd])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'day');

        $registrationsByDay = User::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereBetween('created_at', [$trendStart, $trendEnd])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'day');

        $series = $this->buildTrendSeries($trendStart, $revenueByDay, $registrationsByDay);

        $zoneRevenue = Transaction::query()
            ->selectRaw("COALESCE(zone, 'connection') as zone_name, COALESCE(SUM(amount), 0) as total")
            ->where('status', 'COMPLETED')
            ->groupBy('zone_name')
            ->pluck('total', 'zone_name');

        $admins = User::query()
            ->where('role', 'admin')
            ->orderBy('email')
            ->get(['id', 'name', 'email', 'status', 'created_at', 'last_login']);

        $recentTransactions = Transaction::query()
            ->orderByDesc('created_at')
            ->take(8)
            ->get([
                'id',
                'transaction_id',
                'buyer_name',
                'user_email',
                'amount',
                'currency',
                'status',
                'zone',
                'reference',
                'created_at',
            ]);

        $activeGatewaySetting = AppSetting::where('key', 'active_payment_gateway')->first();
        $activeGatewayValue = is_array($activeGatewaySetting?->value) ? $activeGatewaySetting->value : [];
        $activeGateway = $activeGatewayValue['provider'] ?? 'sonicpesa';

        return view('connection', [
            'adminUser' => $admin,
            'activeGateway' => $activeGateway,
            'highlights' => [
                'total_revenue' => $totalRevenue,
                'monthly_revenue' => $monthlyRevenue,
                'today_revenue' => $todayRevenue,
                'completed_orders' => $completedOrders,
                'pending_orders' => $pendingOrders,
                'failed_orders' => $failedOrders,
                'total_users' => $totalUsers,
                'admins_count' => $adminsCount,
                'active_users' => $activeUsers,
                'banned_users' => $bannedUsers,
                'registrations_this_month' => $registrationsThisMonth,
                'registrations_previous_month' => $registrationsPreviousMonth,
                'active_rate' => $activeRate,
                'registration_growth' => $registrationGrowth,
            ],
            'chartData' => [
                'labels' => $series['labels'],
                'revenue' => $series['revenue'],
                'registrations' => $series['registrations'],
                'zoneLabels' => array_map(
                    fn (string $label) => $label === 'wakubwa' ? 'Wakubwa' : 'Connection',
                    array_keys($zoneRevenue->all())
                ),
                'zoneRevenue' => array_values($zoneRevenue->map(fn ($value) => (int) $value)->all()),
            ],
            'admins' => $admins,
            'recentTransactions' => $recentTransactions,
            'rangeLabel' => $trendStart->format('M j').' - '.$today->format('M j, Y'),
            'generatedAt' => $today,
        ]);
    }

    public function switchGateway(Request $request): RedirectResponse
    {
        $admin = ConnectionAccessController::authenticatedAdmin($request);
        if (! $admin) {
            return redirect()->route('connection.login');
        }

        $validated = $request->validate([
            'provider' => 'required|string|in:sonicpesa,mobilipa',
        ]);

        AppSetting::updateOrCreate(
            ['key' => 'active_payment_gateway'],
            ['value' => ['provider' => $validated['provider']]]
        );

        return redirect()->route('connection.dashboard')
            ->with('status', 'Payment gateway switched to '.ucfirst($validated['provider']).'.');
    }

    private function growthPercentage(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @param  Collection<int|string, mixed>  $revenueByDay
     * @param  Collection<int|string, mixed>  $registrationsByDay
     * @return array{labels: array<int, string>, revenue: array<int, int>, registrations: array<int, int>}
     */
    private function buildTrendSeries(
        CarbonImmutable $start,
        Collection $revenueByDay,
        Collection $registrationsByDay
    ): array {
        $labels = [];
        $revenue = [];
        $registrations = [];

        for ($offset = 0; $offset < 30; $offset++) {
            $day = $start->addDays($offset);
            $dayKey = $day->toDateString();

            $labels[] = $day->format('M j');
            $revenue[] = (int) ($revenueByDay[$dayKey] ?? 0);
            $registrations[] = (int) ($registrationsByDay[$dayKey] ?? 0);
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'registrations' => $registrations,
        ];
    }
}
