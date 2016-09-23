<?php

use Illuminate\Support\Facades\App;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* ClientConnectionTest
*/
class ClientConnectionTest extends TestCase {

    protected $use_database = true;

    public function testConnectUserToClient() {
        $this->seed('OAuthClientsTableSeeder');
        $this->seed('OAuthScopesTableSeeder');

        $user_helper = app('UserHelper')->setTestCase($this);
        $client_repository = app('Tokenpass\Repositories\OAuthClientRepository');
        $connection_repository = app('Tokenpass\Repositories\ClientConnectionRepository');
        $client = $client_repository->findById('client1id');
        $client_two = $client_repository->findById('client2id');
        PHPUnit::assertNotEmpty($client);
        PHPUnit::assertNotEmpty($client_two);

        // create a new user
        $user_one = $user_helper->createNewUser();
        $user_two = $user_helper->createNewUser([
            'username'        => 'johndoe2',
            'email'           => 'johndoe2@tokenly.com',
            'confirmed_email' => 'johndoe2@tokenly.com',
        ]);
        $user_three = $user_helper->createNewUser([
            'username'        => 'johndoe3',
            'email'           => 'johndoe3@tokenly.com',
            'confirmed_email' => 'johndoe3@tokenly.com',
        ]);

        // Connect user two to a client
        $connection_repository->connectUserToClient($user_one, $client);
        $connection_repository->connectUserToClient($user_two, $client);
        $connection_repository->connectUserToClient($user_two, $client_two);

        // test client connections
        PHPUnit::assertTrue($connection_repository->isUserConnectedToClient($user_one, $client));
        PHPUnit::assertTrue($connection_repository->isUserConnectedToClient($user_two, $client));
        PHPUnit::assertFalse($connection_repository->isUserConnectedToClient($user_three, $client));
        PHPUnit::assertFalse($connection_repository->isUserConnectedToClient($user_one, $client_two));
        PHPUnit::assertTrue($connection_repository->isUserConnectedToClient($user_two, $client_two));
        PHPUnit::assertFalse($connection_repository->isUserConnectedToClient($user_three, $client_two));

        // disconnect client
        $connection_repository->disconnectUserFromClient($user_one, $client);
        PHPUnit::assertFalse($connection_repository->isUserConnectedToClient($user_one, $client));

    }


    ////////////////////////////////////////////////////////////////////////



}
