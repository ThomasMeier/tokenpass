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
        $balances_list = $this->address_repository->getCombinedAddressBalancesByUser($user['id'], $public_only, $active_only, $verified_only);

        return collect($balances_list)->map(function($entry) {
            return [
                'asset'      => $entry->asset,
                'name'       => $entry->asset, // (BVAM name here)
                'balance'    => CurrencyUtil::satoshisToValue($entry->balance),
                'balanceSat' => (string)$entry->balance,
            ];
        })->toArray();
    }



    
}
