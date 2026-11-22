<?php

namespace App\Http\Controllers;

use App\Http\Requests\DonationRequest;
use App\Http\Resources\DonationResource;
use App\Models\Donation;
use App\Services\FundingService;
use App\Services\SimulatedProvider;
use App\Services\SimulatedReturns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class DonationController extends Controller
{
    public function policy(): JsonResponse
    {
        return response()->json(['data' => [
            'version' => config('funding.policy_version'),
            'simulated' => SimulatedProvider::enabled(),
            'pool_holding_days' => config('funding.pool_holding_days'),
            'terms' => [
                'fr' => __('funding.terms', ['days' => config('funding.pool_holding_days')], 'fr'),
                'ar' => __('funding.terms', ['days' => config('funding.pool_holding_days')], 'ar'),
            ],
        ]])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): JsonResponse
    {
        $page = Donation::where('donor_id', $request->user()->id)->orderByDesc('id')->cursorPaginate(25);

        return DonationResource::collection($page)->response()->header('Cache-Control', 'no-store');
    }

    public function create(DonationRequest $request, FundingService $funding): JsonResponse
    {
        abort_unless(SimulatedProvider::enabled(), 404);
        $donation = $funding->create($request->user(), $request->validated(), $request->validated('operation_key'));

        return DonationResource::make($donation)->response()->setStatusCode(201)->header('Cache-Control', 'no-store');
    }

    public function show(Donation $donation): JsonResponse
    {
        return response()->json(['data' => DonationResource::make($donation)->detail()])->header('Cache-Control', 'no-store');
    }

    public function simulate(DonationRequest $request, Donation $donation, FundingService $funding): JsonResponse
    {
        abort_unless(SimulatedProvider::enabled(), 404);
        $funding->simulate($donation, $request->validated('outcome'));

        return $this->show($donation->refresh());
    }

    public function simulateReturn(DonationRequest $request, Donation $donation, SimulatedReturns $returns): Response
    {
        abort_unless(SimulatedProvider::enabled(), 404);
        $input = $request->validated();
        $returns->process($request->user(), $donation, $input['kind'], (int) $input['amount_minor'], $input['operation_key']);

        return response()->noContent();
    }

    public function webhook(Request $request, SimulatedProvider $provider, FundingService $funding): Response
    {
        abort_unless($provider->authenticated($request->getContent(), $request->header('X-Simulator-Timestamp', ''),
            $request->header('X-Simulator-Signature', '')), 401);
        abort_if(strlen($request->getContent()) > 4096, 413);
        $data = Validator::make($request->json()->all(), [
            'event_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/D'],
            'reference' => ['required', 'string', 'max:100'],
            'outcome' => ['required', 'in:pending,success,failed,cancelled'],
            'amount_minor' => ['required', 'string', 'regex:/^[1-9][0-9]{0,11}$/D'],
            'currency' => ['required', 'in:MAD'],
        ])->validate();
        $funding->receive($data);

        return response()->noContent(202);
    }
}
