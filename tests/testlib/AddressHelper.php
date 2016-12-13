<?php

use Illuminate\Support\Facades\Log;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;
use Tokenpass\Providers\PseudoAddressManager\PseudoAddressManager;

/*
* AddressHelper
*/
class AddressHelper
{

    const SATOSHI = 100000000;

    public function __construct() {
    }


    public function createNewAddressWithoutXChainIDs(User $user=null, $address_override_vars=[]) {
        $address_override_vars['xchain_address_id']  = '';
        $address_override_vars['receive_monitor_id'] = '';
        $address_override_vars['send_monitor_id']    = '';

        return $this->createNewAddress($user, $address_override_vars);
    }

    public function createNewPseudoAddress(User $user=null) {
        if ($user === null) {
            $user = app('UserHelper')->createNewUser();
        }
        return app(PseudoAddressManager::class)->ensurePseudoAddressForUser($user);
    }

    public function createNewAddress(User $user=null, $address_override_vars=[]) {
        if ($user === null) {
            $user = app('UserHelper')->createNewUser();
        }

        $address_vars = array_merge($this->defaultAddressVars($user), $address_override_vars);
        $address = app('Tokenpass\Repositories\AddressRepository')->create($address_vars);

        return $address;
    }

    public function defaultAddressVars(User $user) {
        return [
            'user_id'            => $user['id'],
            'type'               => 'btc',
            'address'            => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD',
            'label'              => 'My First Address',

            'xchain_address_id'  => '11111111-1111-1111-1111-'.substr(md5(uniqid()),-12),
            'receive_monitor_id' => '11111111-1111-1111-2222-'.substr(md5(uniqid()),-12),
            'send_monitor_id'    => '11111111-1111-1111-3333-'.substr(md5(uniqid()),-12),

            'verified'           => true,
            'public'             => true,
            'active_toggle'      => true,
            'login_toggle'       => true,
            'pseudo'             => false,
        ];
    }
    
    public function altAddressVars(User $user){
        $default = $this->defaultAddressVars($user);
        $default['address'] = '1KFHE7w8BhaENAswwryaoccDb6qcT6DbYY';
        return $default;
    }
    
    public function addBalancesToAddress($balances, Address $address) {
        foreach($balances as $token => $balance) {
            DB::Table('address_balances')->insert([
                'address_id' => $address['id'],
                'asset'      => $token,
                'balance'    => $balance * self::SATOSHI,
                'updated_at' => time(),
            ]);
        }
    }

    public function updateAddressBalances($balances, Address $address) {
        foreach($balances as $token => $balance) {
            DB::Table('address_balances')
                ->where('address_id', $address['id'])
                ->where('asset', $token)
                ->update([
                    'balance'    => $balance * self::SATOSHI,
                    'updated_at' => time(),
                ]
            );
        }
    }
}
