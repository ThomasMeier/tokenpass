<?php

use \PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;
use Tokenpass\Models\Provisional;

class LendingTest extends TestCase
{

    protected $use_database = true;
    
    
    public function setupCustomers()
    {
        $this->setupXChainMock();
        
        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a new user and login, give them a verified bitcoin address
        $random_user = $user_helper->randomUserVars();
        $user = $user_helper->createNewUser($random_user);
        $user_helper->loginWithForm($this->app, array('username' => $user->username, 'password' => $random_user['password']));
        $this->user = $user;
        $this->user_address = $address_helper->createNewAddress($this->user);
        
        //create a second user we can use a recipient, also give them a verified bitcoin address
        $random_user2 = $user_helper->randomUserVars();
        $receive_user = $user_helper->createNewUser($random_user2);
        $this->receive_user = $receive_user;
        $this->receive_user_address = $address_helper->createNewAddress($this->receive_user, $address_helper->altAddressVars($this->receive_user));
        
        //create a third user with no verified btc addresses
        $random_user3 = $user_helper->randomUserVars();
        $this->unverified_user = $user_helper->createNewUser($random_user3);
        
        //give the logged in user some balances
        $this->mock_builder->setBalances(['default' => ['confirmed' => ['SOUP' => 5000]]], $this->user_address->xchain_address_id);
        $this->user_address->syncAccountBalancesWithXChain();
    }

    public function testLendingCRUD()
    {
        $this->setupCustomers();

        //try lending with invalid source
        $lend_vars = array('quantity' => 2, 'end_date' => '2030/01/01', 'note' => 'testing', 'lendee' => $this->receive_user->username);
        $response = $this->call('POST', '/inventory/lend/abc123/SOUP', $lend_vars);
        PHPUnit::assertContains('Address not found', Session::get('message'));
        
        //try invalid asset
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/ASDF', $lend_vars);
        PHPUnit::assertContains('Invalid asset', Session::get('message'));              
        
        //try with no quantity
        unset($lend_vars['quantity']);
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('Quantity required', Session::get('message'));   

        //try invalid quantity
        $lend_vars['quantity'] = "0";
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('Invalid quantity', Session::get('message'));        
        
        //try lending with invalid expiration   
        $lend_vars['end_date'] = '1998/01/05';
        $lend_vars['quantity'] = 1.5;
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('Expiration date must be sometime in the future', Session::get('message'));             
        
        //try with no lendee
        unset($lend_vars['lendee']);
        $lend_vars['end_date'] = '2030/01/01';
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('Lendee required', Session::get('message'));  
        
        //try lending to self
        $lend_vars['lendee'] = $this->user->username;
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('Cannot lend to self', Session::get('message'));          
        
        //try lending to an invalid btc address destination
        $lend_vars['lendee'] = 'qwertyuiop';
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('Please enter a valid bitcoin address', Session::get('message'));        
        
        //try lending to destination that is same as source
        $lend_vars['lendee'] = $this->user_address->address;
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('Cannot lend to source address', Session::get('message'));            
        
        //try lending with more tokens than you have
        $lend_vars['lendee'] = $this->receive_user->username;
        $lend_vars['quantity'] = 99999999999999999999;
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('Not enough real balance to lend this amount', Session::get('message'));           
        
        //make a proper loan to a user
        $lend_vars['quantity'] = 1.5;
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('SOUP succesfully lent!', Session::get('message'));              
        
        //make another proper loan to a bitcoin address
        $lend_vars['lendee'] = $this->receive_user_address->address;
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('SOUP succesfully lent!', Session::get('message'));    
        
        //make sure loan actually saved
        $get_loan = Provisional::where('user_id', $this->user->id)->first();
        PHPUnit::assertInternalType('object', $get_loan);
        
        //update loan with invalid expiration
        $update_vars = array('end_date' => '1989/03/30');
        $response = $this->call('POST', '/inventory/lend/'.$get_loan->id.'/edit', $update_vars);
        PHPUnit::assertContains('Expiration date must be sometime in the future', Session::get('message'));  
        
        //try updating non existant loan
        $update_vars['end_date'] = '2025/02/05';
        $response = $this->call('POST', '/inventory/lend/asd/edit', $update_vars);
        PHPUnit::assertContains('TCA loan not found', Session::get('message'));        
                
        //update loan with proper expiration
        $response = $this->call('POST', '/inventory/lend/'.$get_loan->id.'/edit', $update_vars);
        PHPUnit::assertContains('Loan successfully modified!', Session::get('message'));        
        
        //try deleting a non existant loan
        $response = $this->call('GET', '/inventory/lend/asd/delete', $update_vars);
        PHPUnit::assertContains('TCA loan not found', Session::get('message'));             
        
        //delete loan for real
        $response = $this->call('GET', '/inventory/lend/'.$get_loan->id.'/delete', $update_vars);
        PHPUnit::assertContains('TCA loan cancelled', Session::get('message'));            
    }
    
    public function testLendingExpiration()
    {
        if(!isset($this->user) OR !$this->user){
            $this->setupCustomers();
        }

        //make a loan
        $lend_vars = array('quantity' => 2, 'end_date' => '2030/01/01', 'note' => 'testing', 'lendee' => $this->receive_user->username);
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('SOUP succesfully lent!', Session::get('message'));   
        
        //force expiration date
        DB::table('provisional_tca_txs')->update(array('expiration' => 1000));
        
        //run the command
        $get = $this->getConsoleResponse('tokenpass:expireProvisionalTransactions');
        PHPUnit::assertContains('provisional transactions expired', $get);
               
    }
    
    public function testLendingInvalidation()
    {
        if(!isset($this->user) OR !$this->user){
            $this->setupCustomers();
        }
        
        //make a loan
        $lend_vars = array('quantity' => 50, 'end_date' => '2030/01/01', 'note' => 'test invalidation', 'lendee' => $this->receive_user->username);
        $response = $this->call('POST', '/inventory/lend/'.$this->user_address->address.'/SOUP', $lend_vars);
        PHPUnit::assertContains('SOUP succesfully lent!', Session::get('message')); 
        
        //make sure it really happened
        $get_loan = Provisional::where('user_id', $this->user->id)->where('note', 'test invalidation')->first();
        PHPUnit::assertInternalType('object', $get_loan);
        
        //update balances on xchain, which should automatically invalidate the above loan
        $this->mock_builder->setBalances(['default' => ['confirmed' => ['SOUP' => 1]]], $this->user_address->xchain_address_id);
        $this->user_address->syncWithXChain();      
        
        //make sure loan is really gone
        $get_loan = Provisional::where('user_id', $this->user->id)->where('note', 'test invalidation')->first();
        PHPUnit::assertNull($get_loan);
        
    }

    protected function setupXChainMock() {
        $this->mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $this->xchain_mock_recorder = $this->mock_builder->installXChainMockClient($this);
    }
    
    protected function getConsoleResponse($command)
    {
        $kernel = $this->app->make(Illuminate\Contracts\Console\Kernel::class);
        $status = $kernel->handle(
            $input = new Symfony\Component\Console\Input\ArrayInput([
                'command' => $command, 
            ]),
            $output = new Symfony\Component\Console\Output\BufferedOutput
        );

        return $output->fetch();
    }    
 
}
