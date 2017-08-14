<?php

namespace Tokenpass\Console\Commands;

use Illuminate\Console\Command;
use Tokenpass\Models\Address;
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
            throw new \Exception('User not found');
        }

        $promise_txs = $provisionalRepository->findPromiseTx($email);

        $address = Address::getAddressList($user->id, 1, 1, true)->first();

        if(empty($address)) {
            throw new \Exception('Address could not be found');
        }

        foreach ($promise_txs as $promise_tx) {
            $promise_tx->destination = $address->address;
            $promise_tx->save();
        }
    }
}
