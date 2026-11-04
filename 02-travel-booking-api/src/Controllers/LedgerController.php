<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Models\Ledger;

final class LedgerController
{
    public function __construct(private Ledger $ledger)
    {
    }

    public function index(): void
    {
        Http::json(['status' => 'success', 'data' => $this->ledger->all()]);
    }

    public function summary(): void
    {
        Http::json(['status' => 'success', 'data' => $this->ledger->summaryByProduct()]);
    }
}
