<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminWorkspaceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DebtApplicationController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\GeographyController;
use App\Http\Controllers\PublicDiscoveryController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\VerificationController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Models\DebtCase;
use App\Models\Donation;
use App\Models\Shop;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['data' => ['status' => 'ok']])->header('Cache-Control', 'no-store'));
$geographyLevels = ['regions', 'cities', 'localities'];
Route::get('/v1/{level}', [GeographyController::class, 'publicIndex'])->whereIn('level', $geographyLevels);
Route::get('/v1/cases', [PublicDiscoveryController::class, 'index']);
Route::get('/v1/cases/{reference}', [PublicDiscoveryController::class, 'show'])->whereUuid('reference');
Route::get('/v1/discovery', [PublicDiscoveryController::class, 'home']);
Route::get('/v1/transparency', [SettlementController::class, 'transparency'])->middleware('throttle:60,1');
Route::get('/v1/donation-policies/current', [DonationController::class, 'policy']);
Route::post('/v1/webhooks/payments/simulator', [DonationController::class, 'webhook'])->middleware('throttle:120,1');
Route::prefix('v1')->middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () use ($geographyLevels) {
    Route::get('/admin/overview', [AdminWorkspaceController::class, 'overview']);
    Route::get('/admin/regions/activity', [AdminWorkspaceController::class, 'regions']);
    Route::get('/admin/audit', [AdminWorkspaceController::class, 'audit']);
    Route::get('/admin/financial-search/{kind}', [AdminWorkspaceController::class, 'finance'])
        ->whereIn('kind', ['donations', 'allocations', 'transactions', 'refunds'])->middleware('throttle:60,1');
    foreach (['admin', 'shop'] as $audience) {
        Route::get("/$audience/settlements", [SettlementController::class, 'index'])->defaults('audience', $audience);
        Route::post("/$audience/settlements/{case}/{payment}", [SettlementController::class, 'act'])
            ->whereUuid(['case', 'payment'])->defaults('audience', $audience)->middleware('throttle:30,1');
    }
    Route::post('/admin/settlements/{case}', [SettlementController::class, 'act'])
        ->whereUuid('case')->defaults('audience', 'admin')->middleware('throttle:30,1');
    Route::get('/admin/finance', [SettlementController::class, 'history']);
    Route::get('/admin/finance/journal.csv', [SettlementController::class, 'export'])->middleware('throttle:10,1');
    Route::get('/admin/finance/reconciliation.csv', [SettlementController::class, 'export'])
        ->defaults('report', 'reconciliation')->middleware('throttle:10,1');
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/donations', [DonationController::class, 'index'])->can('create', Donation::class);
    Route::post('/donations', [DonationController::class, 'create'])->can('create', Donation::class)->middleware('throttle:30,1');
    Route::get('/donations/{donation}', [DonationController::class, 'show'])->whereUuid('donation')->can('view', 'donation');
    Route::post('/donations/{donation}/simulation', [DonationController::class, 'simulate'])
        ->whereUuid('donation')->can('view', 'donation')->middleware('throttle:30,1');
    Route::post('/admin/donations/{donation}/simulate-return', [DonationController::class, 'simulateReturn'])
        ->whereUuid('donation')->can('manageReturns', Donation::class)->middleware('throttle:30,1');
    Route::patch('/me', [AccountController::class, 'update']);
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:3,1');
    Route::get('/admin/users', [AccountController::class, 'index']);
    Route::patch('/admin/users/{user}/status', [AccountController::class, 'status'])->middleware('throttle:account-status');

    Route::get('/shop/shops', [ShopController::class, 'index']);
    Route::post('/shop/shops', [ShopController::class, 'create'])->can('create', Shop::class);
    Route::get('/shops/{shop}', [ShopController::class, 'show'])->whereUuid('shop');
    Route::patch('/shop/shops/{shop}', [ShopController::class, 'update'])->whereUuid('shop')->can('update', 'shop');
    Route::post('/shop/shops/{shop}/documents', [ShopController::class, 'upload'])
        ->whereUuid('shop')->can('update', 'shop')->middleware('throttle:shop-evidence');
    Route::get('/shops/{shop}/documents/{document}', [ShopController::class, 'download'])->whereUuid(['shop', 'document']);
    Route::get('/shops/{shop}/debt-requests', [ShopController::class, 'debtRequests'])->whereUuid('shop');
    Route::post('/shop/shops/{shop}/debt-requests/{debt}/decision', [ShopController::class, 'respondDebt'])
        ->whereUuid(['shop', 'debt'])->can('respond', 'shop');

    Route::get('/admin/shops', [ShopController::class, 'adminIndex']);
    Route::post('/admin/shops/{shop}/review', [ShopController::class, 'review'])->whereUuid('shop')->can('review', Shop::class);
    Route::post('/admin/shops/{shop}/debt-requests', [ShopController::class, 'requestDebt'])->whereUuid('shop')->can('review', Shop::class);
    Route::get('/admin/geography/{level}', [GeographyController::class, 'adminIndex'])->whereIn('level', $geographyLevels);
    Route::post('/admin/geography/{level}', [GeographyController::class, 'save'])->whereIn('level', $geographyLevels);
    Route::patch('/admin/geography/{level}/{id}', [GeographyController::class, 'save'])->whereIn('level', $geographyLevels)->whereUuid('id');

    Route::get('/beneficiary/shops', [DebtApplicationController::class, 'shops'])->can('create', DebtCase::class);
    Route::get('/beneficiary/cases', [DebtApplicationController::class, 'index'])->defaults('audience', 'beneficiary')->can('create', DebtCase::class);
    Route::post('/beneficiary/cases', [DebtApplicationController::class, 'create'])->can('create', DebtCase::class);
    Route::patch('/beneficiary/cases/{case}', [DebtApplicationController::class, 'update'])->whereUuid('case')->can('own', 'case');
    Route::post('/beneficiary/cases/{case}/submit', [DebtApplicationController::class, 'submit'])->whereUuid('case')->can('own', 'case');
    Route::post('/beneficiary/cases/{case}/cancel', [DebtApplicationController::class, 'cancel'])->whereUuid('case')->can('own', 'case');
    Route::post('/beneficiary/cases/{case}/documents', [DebtApplicationController::class, 'uploadOwner'])
        ->whereUuid('case')->can('own', 'case')->middleware('throttle:shop-evidence');
    Route::post('/shop/cases/{case}/documents', [DebtApplicationController::class, 'uploadShop'])
        ->whereUuid('case')->can('assigned', 'case')->middleware('throttle:shop-evidence');
    Route::get('/shop/cases', [DebtApplicationController::class, 'index'])->defaults('audience', 'shop')->can('create', Shop::class);
    Route::post('/shop/cases/{case}/verification', [DebtApplicationController::class, 'verify'])->whereUuid('case')->can('assigned', 'case');
    Route::get('/admin/cases', [DebtApplicationController::class, 'index'])->defaults('audience', 'admin')->can('review', DebtCase::class);
    Route::post('/admin/cases/{case}/review', [DebtApplicationController::class, 'review'])->whereUuid('case')->can('review', DebtCase::class);
    Route::post('/admin/cases/{case}/discovery', [PublicDiscoveryController::class, 'review'])->whereUuid('case')->can('review', DebtCase::class);
    foreach (['beneficiary', 'shop', 'admin'] as $audience) {
        Route::get("/$audience/cases/{case}", [DebtApplicationController::class, 'show'])->whereUuid('case')->defaults('audience', $audience);
        Route::get("/$audience/cases/{case}/documents/{document}", [DebtApplicationController::class, 'download'])
            ->whereUuid(['case', 'document'])->defaults('audience', $audience);
    }
});
