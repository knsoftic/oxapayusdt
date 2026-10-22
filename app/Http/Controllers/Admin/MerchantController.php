<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Ledger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MerchantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $merchants = Merchant::with('user')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('business_name', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($q) => $q->where('email', 'like', "%{$search}%"))))
            ->when($request->query('filter') === 'requests', fn ($q) => $q->where('live_enabled', false)->whereNotNull('live_requested_at'))
            ->withCount(['payments as paid_count' => fn ($q) => $q->where('mode', 'live')->whereIn('status', ['paid', 'manual_accept'])])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.merchants.index', compact('merchants', 'search'));
    }

    public function create(): View
    {
        return view('admin.merchants.create');
    }

    /**
     * Merchants can't sign up themselves: the admin opens their account,
     * picks their fees, and hands them the login details.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::min(8)],
            'business_name' => ['required', 'string', 'max:120'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'live_enabled' => ['boolean'],
        ] + $this->feeRules() + $this->limitRules());

        $this->assertLimitsFit(new Merchant, $data);

        $merchant = DB::transaction(function () use ($data, $request) {
            $user = new User(Arr::only($data, ['name', 'email', 'password']));
            // The admin vouches for the address, so no verification email is needed.
            $user->forceFill(['role' => User::ROLE_MERCHANT, 'email_verified_at' => now()])->save();

            return Merchant::create(Arr::only($data, ['business_name', 'website', ...array_keys($this->feeRules() + $this->limitRules())]) + [
                'user_id' => $user->id,
                'live_enabled' => $request->boolean('live_enabled'),
            ]);
        });

        return redirect()->route('admin.merchants.show', $merchant)
            ->with('status', "Merchant created. Send {$data['email']} their password; they can change it in Settings.");
    }

    public function show(Merchant $merchant): View
    {
        return view('admin.merchants.show', [
            'merchant' => $merchant->load('user'),
            'payments' => $merchant->payments()->where('mode', 'live')->latest('id')->limit(10)->get(),
            'payouts' => $merchant->payouts()->latest('id')->limit(10)->get(),
            'entries' => $merchant->ledgerEntries()->latest('id')->limit(15)->get(),
        ]);
    }

    /**
     * Change a merchant's login name, email or password, or their business name.
     */
    public function updateAccount(Request $request, Merchant $merchant): RedirectResponse
    {
        $user = $merchant->user;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'business_name' => ['required', 'string', 'max:120'],
            'password' => ['nullable', Password::min(8)],
        ]);

        $user->fill(Arr::only($data, ['name', 'email']));

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
            $user->remember_token = Str::random(60); // log out "remember me" sessions
        }

        $user->save();
        $merchant->update(['business_name' => $data['business_name']]);

        return back()->with('status', filled($data['password'] ?? null) ? 'Login details saved, including the new password.' : 'Login details saved.');
    }

    /**
     * Fees and amount limits for one merchant.
     */
    public function updateFees(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate($this->feeRules() + $this->limitRules());

        $this->assertLimitsFit($merchant, $data);
        $merchant->update($data);

        return back()->with('status', 'Fees and limits saved. They apply to new payments and withdrawals.');
    }

    /**
     * Per-merchant amount limits in USD; an empty field means "use the platform default".
     *
     * @return array<string, list<string>>
     */
    protected function limitRules(): array
    {
        return [
            'limit_payin_min' => ['nullable', 'numeric', 'min:0.5', 'max:1000000'],
            'limit_payin_max' => ['nullable', 'numeric', 'min:0.5', 'max:1000000'],
            'limit_payout_min' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
            'limit_payout_max' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
        ];
    }

    /**
     * The effective minimum (override or default) must not be above the effective maximum,
     * or the merchant could never take a payment or withdraw.
     *
     * @throws ValidationException
     */
    protected function assertLimitsFit(Merchant $merchant, array $data): void
    {
        $probe = (clone $merchant)->fill(Arr::only($data, array_keys($this->limitRules())));

        if ($probe->payinMax() !== null && $probe->payinMin() > $probe->payinMax()) {
            throw ValidationException::withMessages(['limit_payin_max' => 'The maximum payment ('.Money::usd($probe->payinMax()).') is below the minimum ('.Money::usd($probe->payinMin()).').']);
        }

        if ($probe->payoutMax() !== null && $probe->payoutMin() > $probe->payoutMax()) {
            throw ValidationException::withMessages(['limit_payout_max' => 'The maximum withdrawal ('.Money::usd($probe->payoutMax()).') is below the minimum ('.Money::usd($probe->payoutMin()).').']);
        }
    }

    /**
     * Per-merchant fee overrides; an empty field means "use the platform default".
     *
     * @return array<string, list<string>>
     */
    protected function feeRules(): array
    {
        return [
            'fee_payin_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'fee_payin_fixed' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fee_payout_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'fee_payout_fixed' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    public function toggleLive(Merchant $merchant): RedirectResponse
    {
        $merchant->update(['live_enabled' => ! $merchant->live_enabled]);

        return back()->with('status', $merchant->live_enabled ? 'Live payments activated.' : 'Live payments turned off.');
    }

    public function toggleStatus(Merchant $merchant): RedirectResponse
    {
        $merchant->update([
            'status' => $merchant->isActive() ? Merchant::STATUS_SUSPENDED : Merchant::STATUS_ACTIVE,
        ]);

        return back()->with('status', $merchant->isActive() ? 'Merchant reactivated.' : 'Merchant suspended. Their API keys and logins stop working.');
    }

    /**
     * Manual balance correction, e.g. for an underpaid payment settled by hand.
     */
    public function adjust(Request $request, Merchant $merchant, Ledger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['test', 'live'])],
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'note' => ['required', 'string', 'max:200'],
        ]);

        $cents = Money::cents($data['amount']);

        if ($data['direction'] === 'debit') {
            if ($cents > Money::cents($merchant->balance($data['mode']))) {
                return back()->withErrors(['amount' => 'A debit cannot be more than the current balance.']);
            }

            $cents = -$cents;
        }

        $ledger->adjust($merchant, $data['mode'], $cents, $data['note'], $request->user()->id);

        return back()->with('status', 'Balance adjusted by '.Money::usd($cents / 100).'.');
    }
}
