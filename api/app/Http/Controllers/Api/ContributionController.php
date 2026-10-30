<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cycles\AnnulPaymentRequest;
use App\Http\Requests\Cycles\RegisterPaymentRequest;
use App\Http\Resources\SavingsContributionResource;
use App\Models\SavingsContribution;
use App\Models\SavingsContributionPayment;
use App\Services\ContributionService;

class ContributionController extends Controller
{
    public function __construct(private readonly ContributionService $contributions) {}

    public function storePayment(RegisterPaymentRequest $request, SavingsContribution $contribution)
    {
        $this->authorize('registerMovement', $contribution->cycle);

        $updated = $this->contributions->registerPayment(
            $contribution,
            $request->user(),
            (string) $request->validated('amount'),
            $request->validated('paid_at'),
            $request->validated('observations')
        );

        return $this->success(new SavingsContributionResource($updated), 'Aporte registrado.');
    }

    public function annulPayment(AnnulPaymentRequest $request, SavingsContributionPayment $payment)
    {
        $this->authorize('registerMovement', $payment->contribution->cycle);

        $updated = $this->contributions->annulPayment(
            $payment,
            $request->user(),
            $request->validated('reason')
        );

        return $this->success(new SavingsContributionResource($updated), 'Pago anulado.');
    }
}
