<?php

namespace App\Http\Resources;

use App\Services\DonationLedger;
use App\Services\SimulatedProvider;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class DonationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = DB::table('payment_transactions')->where('donation_id', $this->id)->first();
        $data = [
            'id' => $this->id, 'scope' => $this->scope, 'amount_minor' => (string) $this->amount_minor,
            'currency' => 'MAD', 'simulated' => true, 'policy_version' => $this->policy_version,
            'payment_status' => $payment->status, 'allocation_status' => $this->allocation_status,
            'confirmed_at' => $payment->confirmed_at, 'created_at' => $this->created_at,
            'whatsapp_pledge' => (bool) $this->whatsapp_pledge,
            'simulation_available' => SimulatedProvider::enabled(),
        ];
        if ($this->debt_case_id !== null) {
            $data['case_reference'] = DB::table('debt_cases')->where('id', $this->debt_case_id)->value('public_reference');
        }

        return $data;
    }

    public function detail(): array
    {
        $data = $this->resolve();
        $payment = DB::table('payment_transactions')->where('donation_id', $this->id)->first();
        $ledger = app(DonationLedger::class);
        $balance = $payment->status === 'confirmed' ? $ledger->balance($payment) : ['allocated' => 0, 'returned' => 0, 'reserved' => 0, 'available' => 0];
        $allocations = $ledger->allocations($payment->id)->join('debt_cases as c', 'c.id', '=', 'amounts.debt_case_id')
            ->join('shops as shop', 'shop.id', '=', 'c.shop_id')
            ->orderBy('amounts.id')->get(['amounts.id', 'amounts.net_minor', 'amounts.amount_minor', 'c.public_reference',
                'shop.contact_phone', 'shop.status as shop_status']);
        $payouts = DB::table('shop_payment_lines as line')->join('shop_payments as payment', 'payment.id', '=', 'line.shop_payment_id')
            ->whereIn('line.donation_allocation_id', $allocations->pluck('id'))
            ->get(['line.donation_allocation_id', 'line.amount_minor', 'payment.status'])->groupBy('donation_allocation_id');

        $external = $this->whatsapp_pledge ? DB::table('external_debt_payments')->where('donation_id', $this->id)
            ->first(['status', 'amount_minor']) : null;

        return $data + [
            'external_receipt' => $external ? ['status' => $external->status, 'amount_minor' => (string) $external->amount_minor] : null,
            'captured_minor' => $payment->status === 'confirmed' ? (string) $payment->amount_minor : '0',
            'allocated_minor' => (string) $balance['allocated'], 'returned_minor' => (string) $balance['returned'],
            'return_pending_minor' => (string) $balance['reserved'], 'unallocated_minor' => (string) max(0, $balance['available']),
            'allocations' => $allocations->map(fn ($item) => [
                'id' => $item->id, 'case_reference' => $item->public_reference, 'amount_minor' => (string) $item->net_minor,
                'original_minor' => (string) $item->amount_minor,
                'shop_whatsapp_url' => $item->net_minor > 0 && $item->shop_status === 'verified'
                    ? PhoneNumber::whatsappUrl($item->contact_phone)
                    : null,
                'settlements' => collect($payouts->get($item->id))->map(fn ($line) => [
                    'amount_minor' => (string) $line->amount_minor, 'status' => $line->status,
                ])->values(),
            ]),
            'returns' => DB::table('refunds')->where('payment_transaction_id', $payment->id)->orderBy('id')
                ->get(['kind', 'status', 'amount_minor', 'reason'])->map(fn ($item) => [
                    'kind' => $item->kind, 'status' => $item->status, 'amount_minor' => (string) $item->amount_minor, 'reason' => $item->reason,
                ]),
        ];
    }
}
