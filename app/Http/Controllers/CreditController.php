<?php

namespace App\Http\Controllers;

use App\Billing\CreditLedger;
use App\Billing\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Fake purchase: no payment, just a ledger entry. */
class CreditController extends Controller
{
    public function store(Request $request, CreditLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', Rule::in(config('billing.credit_packs'))],
        ]);

        $ledger->purchase($request->user(), $data['amount'] * Money::NANOS_PER_DOLLAR, "Bought \${$data['amount']} of credits");

        return back()->with('success', "Added \${$data['amount']}.00 to your balance.");
    }
}
