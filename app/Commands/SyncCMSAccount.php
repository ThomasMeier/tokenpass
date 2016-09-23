<?php

namespace Tokenpass\Commands;

use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Support\Facades\Log;
use Tokenpass\Commands\Command;
use Tokenpass\Models\User;
use Tokenpass\Models\Address;
use Tokenpass\Providers\CMSAuth\Util;
use Illuminate\Foundation\Bus\DispatchesJobs;

class SyncCMSAccount extends Command implements SelfHandling
{
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct($user, $cms_credentials)
    {
		$this->cms_loader = app('Tokenpass\Providers\CMSAuth\CMSAccountLoader');
        $this->accounts_user = $user;
        try{
			$this->cms_user = $this->cms_loader->getFullUserInfoWithLogin($cms_credentials['username'], $cms_credentials['password']);
		}
		catch(\Exception $e){
			$this->cms_user = false;
		}
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        if(!$this->cms_user){
			return false;
		}
		
		//load in all cryptocurrency addresses from CMS account
		$address_list = $this->cms_loader->getUserCoinAddresses($this->cms_user);
		$current_list = Address::getAddressList($this->accounts_user->id, null, null);
		$used = array();
		$used_rows = array();
		$stamp = date('Y-m-d H:i:s');
		if($current_list AND count($current_list) > 0){
			foreach($current_list as $row){
				$used[] = $row->address;
				$used_rows[$row->address] = $row;
			}
		}
		foreach($address_list as $row){
			if(!in_array($row['address'], $used)){
                $exists = Address::where('address', $row['address'])->first();
                if(!$exists){
                    $address = app('Tokenpass\Repositories\AddressRepository')->create([
                        'user_id'    => $this->accounts_user->id,
                        'type'       => $row['type'],
                        'address'    => $row['address'],
                        'label'      => trim($row['label']),
                        'verified'   => $row['verified'],
                        'public'     => $row['public'],
                        'created_at' => $row['submitDate'],
                        'updated_at' => $stamp,
                    ]);

                    if ($address['verified']) {
                        // make sure to sync the new address with any xchain balances
                        $address->syncWithXChain();
                    }
                }
			}
			elseif(isset($used_rows[$row['address']])){
				$used_row = $used_rows[$row['address']];
				if($row['label'] != $used_row->label
					OR $row['verified'] != $used_row->verified
					OR $row['public'] != $used_row->public){
						
					$used_row->label = $row['label'];
					$used_row->verified = $row['verified'];
					$used_row->public = $row['public'];
					$used_row->save();
				}
			}
		}
		return true;
    }
}
