<?php

namespace App\Modules\Points\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logs\Services\ActivityLogger;
use App\Modules\Logs\Services\SystemLogger;
use App\Modules\Points\Http\Requests\AdjustPointsRequest;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Points\Services\PointsService;
use App\Modules\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PointsController extends Controller
{
    public function __construct(
        private readonly PointsService $points,
        private readonly ActivityLogger $activityLogs,
        private readonly SystemLogger $systemLogs,
    ) {}

    public function mine(Request $request): Response
    {
        abort_if($request->user()?->is_admin, 403);

        return $this->showPoints($request->user(), false);
    }

    public function show(Request $request, User $customer): Response
    {
        abort_unless($request->user()?->is_admin && ! $customer->is_admin, 404);

        return $this->showPoints($customer, true);
    }

    public function adjust(AdjustPointsRequest $request, User $customer): RedirectResponse
    {
        abort_if($customer->is_admin, 404);
        $attributes = $request->validated();
        /** @var array{action: string, description: string, ledger_id: int, metadata: array<string, mixed>}|null $systemLog */
        $systemLog = null;

        DB::transaction(function () use ($request, $customer, $attributes, &$systemLog): void {
            $lockedCustomer = User::query()->lockForUpdate()->findOrFail($customer->id);
            if (PointsLedger::query()->where('idempotency_key', $attributes['idempotency_key'])->exists()) {
                return;
            }

            $points  = (int) $attributes['points'];
            $balance = $this->points->currentBalance($lockedCustomer);

            if ($points < 0 && abs($points) > $balance) {
                throw ValidationException::withMessages([
                    'points' => 'This deduction would reduce the customer balance below zero.',
                ]);
            }

            $ledger = $this->points->createAdminAdjustment(
                $lockedCustomer,
                $points,
                trim($attributes['reason']),
                $request->user(),
                $attributes['idempotency_key'],
            );
            $pointsChanged = abs($ledger->points);
            $isAddition    = $ledger->points > 0;
            $systemLog     = [
                'action'      => $isAddition ? 'points_added' : 'points_deducted',
                'description' => $isAddition ? "{$pointsChanged} points added by admin." : "{$pointsChanged} points deducted by admin.",
                'ledger_id'   => $ledger->id,
                'metadata'    => [
                    'ledger_id'                                      => $ledger->id,
                    'customer_id'                                    => $lockedCustomer->id,
                    'admin_id'                                       => $request->user()->id,
                    'previous_points'                                => $balance,
                    $isAddition ? 'points_added' : 'points_deducted' => $pointsChanged,
                    'new_points'                                     => $this->points->currentBalance($lockedCustomer),
                    'reason'                                         => trim($attributes['reason']),
                ],
            ];
            $change = $points > 0 ? "+{$points}" : (string) $points;
            $this->activityLogs->record(
                $request->user(),
                'points',
                'adjusted',
                $ledger,
                "Points adjusted for {$lockedCustomer->name}: {$change} points. Reason: ".trim($attributes['reason']),
            );
        });

        if ($systemLog) {
            $this->systemLogs->record(
                type: 'activity',
                action: $systemLog['action'],
                description: $systemLog['description'],
                module: 'points',
                recordId: $systemLog['ledger_id'],
                status: 'completed',
                metadata: $systemLog['metadata'],
            );
        }

        return to_route('customers.show', $customer)->with('success', 'Points adjustment recorded successfully.');
    }

    private function showPoints(User $customer, bool $canAdjust): Response
    {
        $settings = $this->points->settings();
        $balance  = $this->points->currentBalance($customer);
        $ledger   = $customer->pointsLedgers()
            ->with(['order:id,order_number', 'pautangInstallment:id,installment_number'])
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(20)
            ->through(fn (PointsLedger $entry) => $this->ledgerData($entry));

        return Inertia::render('modules/points/Show', [
            'customer' => [
                'id'   => $customer->id,
                'name' => $customer->name,
            ],
            'balance'        => $balance,
            'pesoEquivalent' => number_format($balance * (float) $settings->peso_per_point, 2, '.', ''),
            'pesoPerPoint'   => $settings->peso_per_point,
            'ledger'         => $ledger,
            'canAdjust'      => $canAdjust,
        ]);
    }

    /** @return array<string, mixed> */
    private function ledgerData(PointsLedger $entry): array
    {
        return [
            'id'               => $entry->id,
            'type'             => $entry->type,
            'points'           => $entry->points,
            'description'      => $entry->description,
            'transaction_date' => $entry->transaction_date->toDateString(),
            'order'            => $entry->order ? [
                'id'           => $entry->order->id,
                'order_number' => $entry->order->order_number,
            ] : null,
            'installment_number' => $entry->pautangInstallment?->installment_number,
        ];
    }
}
