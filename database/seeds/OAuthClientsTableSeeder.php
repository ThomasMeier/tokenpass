<?php

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class OAuthClientsTableSeeder extends DatabaseSeeder {

	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		// Model::unguard();

        DB::table('oauth_clients')->delete();

        $datetime = Carbon::now();

        $clients = [
            [
                'id' => 'client1id',
                'secret' => 'client1secret',
                'name' => 'client1',
                'created_at' => $datetime,
                'updated_at' => $datetime,
                'uuid' => Uuid::uuid4()->toString(),
            ],
            [
                'id' => 'client2id',
                'secret' => 'client2secret',
                'name' => 'client2',
                'created_at' => $datetime,
                'updated_at' => $datetime,
                'uuid' => Uuid::uuid4()->toString(),
            ],
        ];

        DB::table('oauth_clients')->insert($clients);

        DB::table('oauth_client_endpoints')->delete();

        $clientEndpoints = [
            [
                'client_id' => 'client1id',
                'redirect_uri' => 'http://example1.com/callback',
                'created_at' => $datetime,
                'updated_at' => $datetime,
            ],
            [
                'client_id' => 'client2id',
                'redirect_uri' => 'http://example2.com/callback',
                'created_at' => $datetime,
                'updated_at' => $datetime,
            ],
        ];

        DB::table('oauth_client_endpoints')->insert($clientEndpoints);
	}

}
