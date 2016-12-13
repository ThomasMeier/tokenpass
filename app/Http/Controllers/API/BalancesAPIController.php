<?php
namespace Tokenpass\Http\Controllers\API;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Log;
use Tokenly\CurrencyLib\CurrencyUtil;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\OAuth\Facade\OAuthGuard;
use Tokenpass\Repositories\AddressRepository;

class BalancesAPIController extends Controller
{

    public function __construct(AddressRepository $address_repository)
    {
        $this->address_repository = $address_repository;
    }


    public function getProtectedBalances() {
        $public_only = false;
        return $this->getBalances($public_only);
    }

    public function getPublicBalances() {
        $public_only = true;
        return $this->getBalances($public_only);
    }


    // ------------------------------------------------------------------------
    
    protected function getBalances($public_only) {
        $input = Input::all();
        $force_refresh = isset($input['refresh']) ? !!$input['refresh'] : false;

        $user = OAuthGuard::user();

        if ($force_refresh) {
            $this->address_repository->updateUserBalances($user->id);
        }

        $active_only   = true;
        $verified_only = true;
        $real_balances_list     = $this->address_repository->getCombinedAddressBalancesByUser($user['id'], $public_only, $active_only, $verified_only);
        $promises_balances_list = $this->address_repository->getCombinedPromisedBalancesByUser($user['id'], $public_only, $active_only, $verified_only);
        $loans_list             = $this->address_repository->getCombinedLoanedBalancesByUser($user['id'], $public_only, $active_only, $verified_only);

        $balances = collect([]);

        // add real balances
        foreach ($real_balances_list as $entry) {
            $this->_sumBalanceEntry($entry->asset, $entry->balance, $balances);
        }

        // add promises
        foreach($promises_balances_list as $entry) {
            $this->_sumBalanceEntry($entry->asset, $entry->balance, $balances);
        }

        // subtract loans
        foreach($loans_list as $entry) {
            $this->_sumBalanceEntry($entry->asset, 0 - $entry->balance, $balances);
        }


        // filter zeros and return
        return $balances
            ->values()
            ->filter(function($balance_entry) {
                return ($balance_entry['balanceSat'] != 0);
            })
            ->map(function($balance_entry) {
                $balance_entry['balanceSat'] = (string)$balance_entry['balanceSat'];
                return $balance_entry;
            })
            ->toArray();
    }

    protected function _sumBalanceEntry($asset, $balance_delta, $balances) {
        if ($balances !== null AND isset($balances[$asset])) {
            $balance_entry = $balances[$asset];
            $balance_entry['balanceSat'] += $balance_delta;
            $balance_entry['balance'] = CurrencyUtil::satoshisToValue($balance_entry['balanceSat']);
        } else {
            $balance_entry = [
                'asset'      => $asset,
                'name'       => $asset, // (BVAM name here)
                'balance'    => CurrencyUtil::satoshisToValue(intval($balance_delta)),
                'balanceSat' => intval($balance_delta),
            ];
        }

        $balances[$asset] = $balance_entry;
    }



    
}
