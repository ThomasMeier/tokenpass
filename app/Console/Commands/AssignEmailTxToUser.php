<?php

namespace Tokenpass\Console\Commands;

use Illuminate\Console\Command;
use Tokenpass\Models\Address;
use Tokenpass\Providers\PseudoAddressManager\PseudoAddressManager;
use Tokenpass\Repositories\ProvisionalRepository;
use Tokenpass\Repositories\UserRepository;


class AssignEmailTxToUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokenpass:assignTx {email : The email of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign promise transactions to a user with the email';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(ProvisionalRepository $provisionalRepository, UserRepository $userRepository)
    {
        $email = $this->argument('email');

        //Check whether there's a user registered with the email
        $user = $userRepository->findByEmail($email);
        if(empty($user)) {
            $this->error('User not found for email '.$email);
            return;
        }
        
        $address_list = Address::getAddressList($user->id, null, 1, true);
        $use_address = false;
        if($address_list AND isset($address_list[0])){
            $use_address = $address_list[0]['address'];
        }

        $promise_txs = $provisionalRepository->findPromiseTx($email);
        foreach ($promise_txs as $promise_tx) {
            //use their first active/primary address, otherwise create a pseudo address
            if(!$use_address){
                $promise_tx->destination = app(PseudoAddressManager::class)->ensurePseudoAddressForUser($user)->address;
            }
            else{
                $promise_tx->destination = $use_address;
            }
            
            //update reference data with their user ID        
            $promise_tx->ref = 'user:' . $user->id;
            $promise_tx->save();
        }

        $client = app('Tokenly\DeliveryClient\Client');
        try {
            $client->updateEmailTx($user->username, $user->email, env('TOKENDELIVERY_TOKENPASS_PRIVILEGED_KEY'));
        } catch (\Exception $e) {
            $this->error('Error updating deliveries: '.$e->getMessage());
        }
    }
}
