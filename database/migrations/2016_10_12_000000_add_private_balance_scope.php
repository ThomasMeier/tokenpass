<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPrivateBalanceScope extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        app('Tokenpass\Repositories\OAuthScopeRepository')->create([
            'id'          => 'private-balances',
            'description' => 'View combined token balances from both public and private pockets',
            'label'       => 'Private Balances',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $scope = app('Tokenpass\Repositories\OAuthScopeRepository')->findById('private-balances');
        if ($scope) {
            app('Tokenpass\Repositories\OAuthScopeRepository')->delete($scope);
        }

    }
}
