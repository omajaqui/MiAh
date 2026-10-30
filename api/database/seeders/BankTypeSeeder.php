<?php

namespace Database\Seeders;

use App\Models\BankType;
use Illuminate\Database\Seeder;

class BankTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Bancolombia', 'Nequi', 'DaviPlata'] as $name) {
            BankType::query()->updateOrCreate(['name' => $name]);
        }
    }
}
