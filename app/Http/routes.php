<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', [
    'as'   => 'welcome',
    'uses' => 'WelcomeController@index'
]);


// -------------------------------------------------------------------------
// User login and registration

// Authentication routes...
Route::get('auth/login',                               ['middleware' => 'tls', 'as' => 'auth.login', 'uses' => 'Auth\AuthLoginController@getLogin']);
Route::get('auth/login/_check',                        ['middleware' => 'tls', 'as' => 'auth.login.check-sig', 'uses' => 'Auth\AuthLoginController@checkForLoginSignature']);
Route::post('auth/login',                              ['middleware' => 'tls', 'uses' => 'Auth\AuthLoginController@postLogin']);
Route::get('auth/logout',                              ['middleware' => 'tls', 'uses' => 'Auth\AuthLoginController@logout']);

// Bitcoin Authentication routes...
Route::get('auth/bitcoin',                             ['middleware' => 'tls', 'as' => 'auth.bitcoin', 'uses' => 'Auth\AuthLoginController@getBitcoinLogin']);
Route::post('auth/bitcoin',                            ['middleware' => 'tls', 'as' => 'auth.bitcoin.post', 'uses' => 'Auth\AuthLoginController@postBitcoinLogin']);
Route::get('auth/sign',                                ['middleware' => 'tls', 'as' => 'auth.sign', 'uses' => 'Auth\AuthLoginController@getSignRequirement']);
Route::post('auth/signed',                             ['middleware' => 'tls', 'as' => 'auth.signed', 'uses' => 'Auth\AuthLoginController@setSigned']);

// Registration routes...
Route::get('auth/register',                            ['middleware' => 'tls', 'uses' => 'Auth\AuthRegisterController@showRegistrationForm']);
Route::post('auth/register',                           ['middleware' => 'tls', 'uses' => 'Auth\AuthRegisterController@register']);

// Update routes...
Route::get('auth/update',                              ['middleware' => 'tls', 'as' => 'auth.update', 'uses' => 'Auth\AuthRegisterController@getUpdate']);
Route::post('auth/update',                             ['middleware' => 'tls', 'uses' => 'Auth\AuthRegisterController@postUpdate']);

// Email confirmations...
Route::get('auth/sendemail',                           ['middleware' => 'tls', 'as' => 'auth.sendemail', 'uses' => 'Auth\EmailConfirmationController@getSendEmail']);
Route::post('auth/sendemail',                          ['middleware' => 'tls', 'uses' => 'Auth\EmailConfirmationController@postSendEmail']);
Route::get('auth/verify/{token}',                      ['middleware' => 'tls', 'as' => 'auth.verify', 'uses' => 'Auth\EmailConfirmationController@verifyEmail']);

// Password reset link request routes...
Route::get('password/email',                           ['middleware' => 'tls', 'uses' => 'Auth\PasswordController@getEmail']);
Route::post('password/email',                          ['middleware' => 'tls', 'uses' => 'Auth\PasswordController@postEmail']);

// Password reset routes...
Route::get('password/reset/{token}',                   ['middleware' => 'tls', 'uses' => 'Auth\PasswordController@getReset']);
Route::post('password/reset',                          ['middleware' => 'tls', 'uses' => 'Auth\PasswordController@postReset']);

// Connected apps routes...
Route::get('auth/connectedapps',                       ['middleware' => 'tls', 'uses' => 'Auth\ConnectedAppsController@getConnectedApps']);
Route::get('auth/revokeapp/{clientid}',                ['middleware' => 'tls', 'uses' => 'Auth\ConnectedAppsController@getRevokeAppForm']);
Route::post('auth/revokeapp/{clientid}',               ['middleware' => 'tls', 'uses' => 'Auth\ConnectedAppsController@postRevokeAppForm']);

//token inventory management
Route::get('inventory',                                ['middleware' => 'tls', 'as' => 'inventory', 'uses' => 'Inventory\InventoryController@index']);
Route::post('inventory/address/new',                   ['middleware' => 'tls', 'as' => 'inventory.pockets.new', 'uses' => 'Inventory\InventoryController@registerAddress']);
Route::post('inventory/address/{address}/edit',        ['middleware' => 'tls', 'as' => 'inventory.pockets.edit', 'uses' => 'Inventory\InventoryController@editAddress']);
Route::post('inventory/address/{address}/verify',      ['middleware' => 'tls', 'as' => 'inventory.pockets.verify', 'uses' => 'Inventory\InventoryController@verifyAddressOwnership']);
Route::post('inventory/address/{address}/click-verify',['middleware' => 'tls', 'as' => 'inventory.pockets.verify', 'uses' => 'Auth\AuthRegisterController@clickVerifyAddress']);
Route::get('inventory/_check',                         ['middleware' => 'tls', 'as' => 'inventory.check-sig', 'uses' => 'Inventory\InventoryController@checkForVerifySignature']);
Route::get('inventory/address/{address}/delete',       ['middleware' => 'tls', 'as' => 'inventory.pockets.delete', 'uses' => 'Inventory\InventoryController@deleteAddress']);
Route::get('inventory/refresh',                        ['middleware' => 'tls', 'as' => 'inventory.force-update', 'uses' => 'Inventory\InventoryController@refreshBalances']);
Route::get('inventory/check-refresh',                  ['middleware' => 'tls', 'as' => 'inventory.check-refresh', 'uses' => 'Inventory\InventoryController@checkPageRefresh']);
Route::post('inventory/asset/{asset}/toggle',          ['middleware' => 'tls', 'as' => 'inventory.asset.toggle', 'uses' => 'Inventory\InventoryController@toggleAsset']);
Route::get('inventory/lend/{id}/delete',               ['middleware' => 'tls', 'as' => 'inventory.lend.delete', 'uses' => 'Inventory\InventoryController@deleteLoan']);
Route::post('inventory/lend/{id}/edit',                ['middleware' => 'tls', 'as' => 'inventory.lend.delete', 'uses' => 'Inventory\InventoryController@editLoan']);
Route::post('inventory/lend/{address}/{asset}',        ['middleware' => 'tls', 'as' => 'inventory.lend', 'uses' => 'Inventory\InventoryController@lendAsset']);

//token dtails
Route::get('token/{token}',                            ['middleware' => 'tls', 'as' => 'token.info', 'uses' => 'Inventory\InventoryController@getTokenDetails']);


// Image routes
Route::post('image/store',                             ['middleware' => 'tls', 'uses' => 'Image\ImageController@store']);
//Route::post('image/show',                              ['middleware' => 'tls', 'uses' => 'Image\ImageController@show']);


// new route/controller for pockets
Route::get('pockets',                                  ['middleware' => 'tls', 'as' => 'inventory.pockets', 'uses' => 'Inventory\InventoryController@getPockets']);

//client applications / API keys
Route::get('auth/apps',                                ['middleware' => 'tls', 'as' => 'auth.apps', 'uses' => 'Auth\AppsController@index']);
Route::post('auth/apps/new',                           ['middleware' => 'tls', 'uses' => 'Auth\AppsController@registerApp']);
Route::post('auth/apps/{app}/edit',                    ['middleware' => 'tls', 'uses' => 'Auth\AppsController@updateApp']);
Route::patch('auth/apps/{app}/regen',                  ['middleware' => 'tls', 'uses' => 'Auth\AppsController@regenerateApp']);
Route::get('auth/apps/{app}/delete',                   ['middleware' => 'tls', 'uses' => 'Auth\AppsController@deleteApp']);

// -------------------------------------------------------------------------
// User routes

// User routes...
Route::get('dashboard', [
    'as'         => 'user.dashboard',
    'middleware' => ['tls', 'auth'],
    'uses'       => 'Accounts\DashboardController@getDashboard'
]);



// -------------------------------------------------------------------------
// oAuth routes

// oAuth authorization form...
Route::get('oauth/authorize', [
    'as'         => 'oauth.authorize.get',
    'middleware' => ['tls', 'check-authorization-params', 'auth', 'csrf',],
    'uses'       => 'OAuth\OAuthController@getAuthorizeForm'
]);
Route::post('oauth/authorize', [
    'as'         => 'oauth.authorize.post',
    'middleware' => ['tls', 'check-authorization-params', 'auth', 'csrf',],
    'uses'       => 'OAuth\OAuthController@postAuthorizeForm'
]);

// oAuth access token
Route::post('oauth/access-token', [
    'as'   => 'oauth.accesstoken',
    'uses' => 'OAuth\OAuthController@postAccessToken'
]);

// oAuth user
Route::get('oauth/user', [
    'as'         => 'oauth.user',
    'middleware' => ['tls', 'oauth',],
    'uses'       => 'OAuth\OAuthController@getUser'
]);


// -------------------------------------------------------------------------
// API endpoints

Route::get('api/v1/tca/check/{username}',                  ['middleware' => 'tls', 'as' => 'api.tca.check', 'uses' => 'API\APIController@checkTokenAccess']);
Route::get('api/v1/tca/check-address/{address}',           ['middleware' => 'tls', 'as' => 'api.tca.check-address', 'uses' => 'API\APIController@checkAddressTokenAccess']);
Route::get('api/v1/tca/check-sign/{address}',              ['middleware' => 'tls', 'as' => 'api.tca.check-sign', 'uses' => 'API\APIController@checkSignRequirement']);
Route::post('api/v1/tca/set-sign',                         ['middleware' => 'tls', 'as' => 'api.tca.set-sign', 'uses' => 'API\APIController@setSignRequirement']);

Route::get('api/v1/tca/addresses/{username}',              ['middleware' => 'tls', 'as' => 'api.tca.addresses', 'uses' => 'API\APIController@getAddresses']);
Route::get('api/v1/tca/addresses/{username}/refresh',      ['middleware' => 'tls', 'as' => 'api.tca.addresses.refresh', 'uses' => 'API\APIController@getRefreshedAddresses']);
Route::get('api/v1/tca/addresses/{username}/{address}',    ['middleware' => 'tls', 'as' => 'api.tca.addresses.details', 'uses' => 'API\APIController@getAddressDetails']);
Route::post('api/v1/tca/addresses/{username}/{address}',   ['middleware' => 'tls', 'as' => 'api.tca.addresses.verify', 'uses' => 'API\APIController@verifyAddress']);
Route::patch('api/v1/tca/addresses/{username}/{address}',  ['middleware' => 'tls', 'as' => 'api.tca.addresses.edit', 'uses' => 'API\APIController@editAddress']);
Route::delete('api/v1/tca/addresses/{username}/{address}', ['middleware' => 'tls', 'as' => 'api.tca.addresses.delete', 'uses' => 'API\APIController@deleteAddress']);
Route::post('api/v1/tca/addresses',                        ['middleware' => 'tls', 'as' => 'api.tca.addresses.new', 'uses' => 'API\APIController@registerAddress']);

Route::get('api/v1/tca/provisional',                       ['middleware' => 'tls', 'as' => 'api.tca.provisional.list', 'uses' => 'API\APIController@getProvisionalTCASourceAddressList']);
Route::post('api/v1/tca/provisional/register',             ['middleware' => 'tls', 'as' => 'api.tca.provisional.register', 'uses' => 'API\APIController@registerProvisionalTCASourceAddress']);
Route::get('api/v1/tca/provisional/tx',                    ['middleware' => 'tls', 'as' => 'api.tca.provisional.tx.list', 'uses' => 'API\APIController@getProvisionalTCATransactionList']);
Route::post('api/v1/tca/provisional/tx',                   ['middleware' => 'tls', 'as' => 'api.tca.provisional.tx.register', 'uses' => 'API\APIController@registerProvisionalTCATransaction']);
Route::get('api/v1/tca/provisional/tx/{id}',               ['middleware' => 'tls', 'as' => 'api.tca.provisional.tx.get', 'uses' => 'API\APIController@getProvisionalTCATransaction']);
Route::patch('api/v1/tca/provisional/tx/{id}',             ['middleware' => 'tls', 'as' => 'api.tca.provisional.tx.update', 'uses' => 'API\APIController@updateProvisionalTCATransaction']);
Route::delete('api/v1/tca/provisional/tx/{id}',            ['middleware' => 'tls', 'as' => 'api.tca.provisional.tx.delete', 'uses' => 'API\APIController@deleteProvisionalTCATransaction']);
Route::delete('api/v1/tca/provisional/{address}',          ['middleware' => 'tls', 'as' => 'api.tca.provisional.delete', 'uses' => 'API\APIController@deleteProvisionalTCASourceAddress']);

Route::post('api/v1/oauth/request',                        ['middleware' => 'tls', 'as' => 'api.oauth.request', 'uses' => 'API\APIController@requestOAuth', 'middleware' => ['check-authorization-params']]);
Route::post('api/v1/oauth/token',                          ['middleware' => 'tls', 'as' => 'api.oauth.token', 'uses' => 'API\APIController@getOAuthToken', 'middleware' => ['check-authorization-params']]);
Route::get('api/v1/oauth/logout',                          ['middleware' => 'tls', 'as' => 'api.oauth.logout', 'uses' => 'API\APIController@invalidateOAuth']);

Route::patch('api/v1/update',                              ['middleware' => 'tls', 'as' => 'api.update-account', 'uses' => 'API\APIController@updateAccount']);
Route::post('api/v1/register',                             ['middleware' => 'tls', 'as' => 'api.register', 'uses' => 'API\APIController@registerAccount']);
Route::post('api/v1/login',                                ['middleware' => 'tls', 'as' => 'api.login', 'uses' => 'API\APIController@loginWithUsernameAndPassword']);

Route::get('api/v1/lookup/address/{address}',              ['middleware' => 'tls', 'as' => 'api.lookup.address', 'uses' => 'API\APIController@lookupUserByAddress']);
Route::post('api/v1/lookup/address/{address}',             ['middleware' => 'tls', 'as' => 'api.lookup.address.post', 'uses' => 'API\APIController@lookupUserByAddress']);
Route::get('api/v1/lookup/user/{username}',                ['middleware' => 'tls', 'as' => 'api.lookup.user', 'uses' => 'API\APIController@lookupAddressByUser']);
Route::post('api/v1/instant-verify/{username}',            ['middleware' => 'tls', 'as' => 'api.instant-verify', 'uses' => 'API\APIController@instantVerifyAddress']);
//Route:get('api/v1/oneclick',                               ['middlware' => 'tls', 'as' => 'api.one.click','uses' => 'API\APIConroller@OneCliCk']);
// XChain Receiver

// webhook notifications

// webhook notifications
Route::post('_xchain_client_receive', ['as' => 'xchain.receive', 'uses' => 'XChain\XChainWebhookController@receive']);
