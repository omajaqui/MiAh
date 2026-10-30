<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankTypeResource;
use App\Models\BankType;

class BankTypeController extends Controller
{
    public function index()
    {
        $types = BankType::query()->orderBy('id')->get();

        return $this->success(BankTypeResource::collection($types)->resolve());
    }
}
