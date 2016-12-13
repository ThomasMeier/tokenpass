<?php

namespace Tokenpass\Providers\PseudoAddressManager;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tokenpass\Models\User;
use Tokenpass\Repositories\AddressRepository;

class PseudoAddressManager
{

    function __construct(AddressRepository $address_repository) {
        $this->address_repository = $address_repository;
    }

    // finds or creates a pseudo address for the user
    public function ensurePseudoAddressForUser(User $user) {
        return DB::transaction(function() use ($user) {
            $pseudo_address_model = $this->address_repository->getPseudoAddressForUser($user);
            if (!$pseudo_address_model) {
                // create a new pseudo address
                $pseudo_address_model = $this->createPseudoAddressForUser($user);
            }

            return $pseudo_address_model;
        });
    }

    // ------------------------------------------------------------------------

    protected function createPseudoAddressForUser(User $user) {
        return $this->address_repository->create([
            'user_id'       => $user['id'],
            'type'          => 'btc',
            'address'       => $this->generatePseudoAddress($user),
            'label'         => 'Placeholder Address',
            'verified'      => true,
            'public'        => false,
            'active_toggle' => true,
            'pseudo'        => true,
        ]);
    }

    protected function generatePseudoAddress(User $user) {
        $username = $user['username'];
        $username_suffix = $username;
        if (strlen($username) > 16) {
            $username_suffix = substr(md5($username), 0, 16);
        }
        $username_suffix = str_pad($username_suffix, 16, 'x', STR_PAD_LEFT);

        return 'U'.str_repeat('x', 17).$username_suffix;
    }
}
