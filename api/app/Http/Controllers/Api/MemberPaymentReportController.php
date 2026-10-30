<?php

namespace App\Http\Controllers\Api;

use App\Enums\MemberPaymentReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cycles\ReviewMemberPaymentReportRequest;
use App\Http\Requests\Cycles\StoreMemberPaymentReportRequest;
use App\Http\Resources\MemberPaymentReportResource;
use App\Models\MemberPaymentReport;
use App\Models\SavingsCycle;
use App\Support\ApiResponse;
use App\Services\MemberPaymentReportService;
use Illuminate\Http\Request;

class MemberPaymentReportController extends Controller
{
    public function __construct(private readonly MemberPaymentReportService $reports) {}

    public function index(Request $request)
    {
        $this->authorize('viewAdmin', SavingsCycle::class);

        $query = MemberPaymentReport::query()
            ->with($this->reports->detailRelations())
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('savings_cycle_id')) {
            $query->where('savings_cycle_id', $request->integer('savings_cycle_id'));
        }

        if ($search = $request->string('search')->toString()) {
            $query->whereHas('person', function ($builder) use ($search) {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $this->success(
            MemberPaymentReportResource::collection(
                $query->paginate($request->integer('per_page', 15))
            )->response()->getData(true)
        );
    }

    public function store(StoreMemberPaymentReportRequest $request, SavingsCycle $savingsCycle)
    {
        if (! $request->user()->canAccessCycle($savingsCycle)) {
            return ApiResponse::error('No puede reportar pagos en ciclos en los que no participa.', 403);
        }

        $report = $this->reports->submit(
            $savingsCycle,
            $request->user(),
            $request->validated('contribution_ids'),
            $request->file('receipt')
        );

        return $this->success(new MemberPaymentReportResource($report), 'Pago reportado. Queda pendiente de validación.', 201);
    }

    public function show(Request $request, MemberPaymentReport $paymentReport)
    {
        $this->assertCanView($request, $paymentReport);

        return $this->success(new MemberPaymentReportResource($paymentReport->load($this->reports->detailRelations())));
    }

    public function receipt(Request $request, MemberPaymentReport $paymentReport)
    {
        $this->assertCanView($request, $paymentReport);

        return $this->reports->receiptResponse($paymentReport);
    }

    public function review(ReviewMemberPaymentReportRequest $request, MemberPaymentReport $paymentReport)
    {
        $this->authorize('viewAdmin', SavingsCycle::class);

        $updated = $this->reports->review(
            $paymentReport,
            $request->user(),
            $request->validated('status'),
            $request->validated('observations')
        );

        $message = $updated->status === MemberPaymentReportStatus::PagoCorrecto
            ? 'Pago marcado como correcto.'
            : 'Pago rechazado.';

        return $this->success(new MemberPaymentReportResource($updated), $message);
    }

    private function assertCanView(Request $request, MemberPaymentReport $report): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isSocio() && (int) $user->person_id === (int) $report->person_id) {
            return;
        }

        abort(403, 'No puede consultar este pago reportado.');
    }
}
