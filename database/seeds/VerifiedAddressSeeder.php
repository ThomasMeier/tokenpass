<?php

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class VerifiedAddress extends DatabaseSeeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         //Model::unguard();

        DB::table('users')->delete();
        DB::table('coin_addresses')->delete();

        $users = array(
            array('id' => '1','uuid' => '124ecaf3-2b78-42ff-a10a-06fc94ceaefe','apitoken' => 'Tq2eCVLmaOB1FFHY','apisecretkey' => 'KHIj0lol1mhUKh1Mn5g25ENHWSSDYglnDbaguhdb','name' => 'MaxSan','username' => 'MaxSan','slug' => 'maxsan','email' => 'max@bitcoinmanchester.org.uk','password' => '$2y$10$oTJhjP/nnd1kYIrb.r/xe.7inBOcF1c4lYhChmas9q3LOMoBDWQ0i','privileges' => NULL,'confirmed_email' => NULL,'confirmation_code' => 'EREeLprh2WgD6gTHxJzVmbxOs2KmLc','confirmation_code_expires_at' => '2016-05-12 01:41:59','remember_token' => 'uQmxmkv23XwXRMOSNzT6JPAxPXBTXrXCPFtJlS4SnUKnLYyW699clFODqRn7','created_at' => '2016-05-11 13:41:59','updated_at' => '2016-05-11 14:34:28'),
            array('id' => '2','uuid' => '855b6951-2d57-4257-a4e5-da173a925be0','apitoken' => 'T0XtcYMzANd742vC','apisecretkey' => 'KsGwcetAFSf2ImpcPVT9i4GjBKsUEwpc8l5PUvuC','name' => 'crafty','username' => 'crafty','slug' => 'crafty','email' => 'crafty@gmx.com','password' => '$2y$10$MIvdpV8xdjw6i7vl7btGyOCVzVMa/YkQC/9ll0bU/i0Hd8YqHBF76','privileges' => NULL,'confirmed_email' => NULL,'confirmation_code' => 'EVNUtNvJEXYzPV8aWGbbW5wsBc642V','confirmation_code_expires_at' => '2016-05-12 02:35:14','remember_token' => 'uAL0Imk10ii7OQ1URiWDjJmsIujMtCVpgVETqQxgaWlYcn9aJxAZhipZTBVX','created_at' => '2016-05-11 14:35:14','updated_at' => '2016-05-11 14:51:03'),
            array('id' => '3','uuid' => '317335ac-e0f6-451e-9a67-3ad9a66b6783','apitoken' => 'TGUeP5Mx84meFHWb','apisecretkey' => 'KKctFGyeWm0ELcGe1YtMev1qpZXfaAZzCZsof3IH','name' => 'werk','username' => 'werk','slug' => 'werk','email' => 'werk@here.com','password' => '$2y$10$JUBlXr3p6eMXZOP40HGBmuklTRG0qFEXFCZGQJVeWaozKoWzLgY5u','privileges' => NULL,'confirmed_email' => NULL,'confirmation_code' => 'EDlN04Bqzug0ADIKAAfPMyAIrpAEkh','confirmation_code_expires_at' => '2016-05-12 02:51:45','remember_token' => '7Iyx1s96udXlxFdgkgWgjgVbbKKVTMnJM98btajgYK5sh19xbGXFP5H6x3vw','created_at' => '2016-05-11 14:51:45','updated_at' => '2016-05-12 18:50:08')
        );

        $coin_addresses = array(
            array('id' => '1','user_id' => '3','type' => 'btc','address' => '17jt7kJJQPMqJwTVCKRWjLBdYcS888t3CU','label' => '','verified' => '1','public' => '0','created_at' => '2016-05-11 14:59:15','updated_at' => '2016-05-11 15:00:13','active_toggle' => '1','uuid' => '95a33464-576a-4e20-bba1-3edc92d1a2b9','xchain_address_id' => 'b634a89e-5e4a-4356-8bed-198d9055433e','receive_monitor_id' => 'f60fe343-8ece-4ac7-b37f-9acc64fd90e4','send_monitor_id' => 'fea4d777-267d-4c0f-96de-ab48594c7ccb'),
            array('id' => '2','user_id' => '3','type' => 'btc','address' => '3Esp6DN7f6tfQE951qwSP5Jxaw6kTpcMdx','label' => '','verified' => '0','public' => '0','created_at' => '2016-05-11 16:03:17','updated_at' => '2016-05-11 16:03:19','active_toggle' => '1','uuid' => '293c1635-f169-4d95-8113-916357f42029','xchain_address_id' => '6af3325f-8cc5-4630-8e0a-5517e4eb0d80','receive_monitor_id' => '1d4765d2-9f68-4717-a50b-9aec1b4dd29f','send_monitor_id' => 'bc267bbc-e758-4b57-9a93-8c8af4435d40')
        );

        DB::table('users')->insert($users);
        DB::table('coin_addresses')->insert($coin_addresses);

    }

}
