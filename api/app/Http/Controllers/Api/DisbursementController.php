<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cycles\AnnulPaymentRequest;
use App\Http\Requests\Cycles\RegisterDisbursementRequest;
use App\Http\Resources\SavingsDisbursementResource;
use App\Models\SavingsDisbursement;
use App\Models\SavingsTurn;
use App\Services\DisbursementService;

class DisbursementController extends Controller
{
    public function __construct(private readonly DisbursementService $disbursements) {}

    public function store(RegisterDisbursementRequest $request, SavingsTurn $turn)
    {
        $this->authorize('registerMovement', $turn->cycle);

        $disbursement = $this->disbursements->register(
            $turn,
            $request->user(),
            $request->validated('delivered_amount'),
            $request->validated('delivered_at'),
            $request->validated('observations')
        );

        return $this->success(new SavingsDisbursementResource($disbursement), 'Entrega registrada.', 201);
    }

    public function annul(AnnulPaymentRequest $request, SavingsDisbursement $disbursement)
    {
        $this->authorize('registerMovement', $disbursement->cycle);

        $updated = $this->disbursements->annul(
            $disbursement,
            $request->user(),
            $request->validated('reason')
        );

        return $this->success(new SavingsDisbursementResource($updated), 'Entrega anulada.');
    }
}
