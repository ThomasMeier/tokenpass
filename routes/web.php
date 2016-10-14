<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [
    'as'   => 'welcome',
    'uses' => 'WelcomeController@index'
]);


// -------------------------------------------------------------------------
// User login and registration

// Authentication routes...
Route::get('auth/login',                               ['as' => 'auth.login', 'uses' => 'Auth\AuthLoginController@getLogin']);
Route::get('auth/login/_check',                        ['as' => 'auth.login.check-sig', 'uses' => 'Auth\AuthLoginController@checkForLoginSignature']);
Route::post('auth/login',                              ['uses' => 'Auth\AuthLoginController@postLogin']);
Route::get('auth/logout',                              ['uses' => 'Auth\AuthLoginController@logout']);

// Bitcoin Authentication routes...
Route::get('auth/bitcoin',                             ['as' => 'auth.bitcoin', 'uses' => 'Auth\AuthLoginController@getBitcoinLogin']);
Route::post('auth/bitcoin',                            ['as' => 'auth.bitcoin.post', 'uses' => 'Auth\AuthLoginController@postBitcoinLogin']);
Route::get('auth/sign',                                ['as' => 'auth.sign', 'uses' => 'Auth\AuthLoginController@getSignRequirement']);
Route::post('auth/signed',                             ['as' => 'auth.signed', 'uses' => 'Auth\AuthLoginController@setSigned']);

// Registration routes...
Route::get('auth/register',                            ['uses' => 'Auth\AuthRegisterController@showRegistrationForm']);
Route::post('auth/register',                           ['uses' => 'Auth\AuthRegisterController@register']);

// Update routes...
Route::get('auth/update',                              ['as' => 'auth.update', 'uses' => 'Auth\AuthRegisterController@getUpdate']);
Route::post('auth/update',                             ['uses' => 'Auth\AuthRegisterController@postUpdate']);

// Email confirmations...
Route::get('auth/sendemail',                           ['as' => 'auth.sendemail', 'uses' => 'Auth\EmailConfirmationController@getSendEmail']);
Route::post('auth/sendemail',                          ['uses' => 'Auth\EmailConfirmationController@postSendEmail']);
Route::get('auth/verify/{token}',                      ['as' => 'auth.verify', 'uses' => 'Auth\EmailConfirmationController@verifyEmail']);

// Password reset link request routes...
Route::get('password/email',                           ['uses' => 'Auth\ForgotPasswordController@showLinkRequestForm']);
Route::post('password/email',                          ['uses' => 'Auth\ForgotPasswordController@sendResetLinkEmail']);

// Password reset routes...
Route::get('password/reset/{token}',                   ['uses' => 'Auth\PasswordController@showResetForm']);
Route::post('password/reset',                          ['uses' => 'Auth\PasswordController@reset']);

// Connected apps routes...
Route::get('auth/connectedapps',                       ['uses' => 'Auth\ConnectedAppsController@getConnectedApps']);
Route::get('auth/revokeapp/{clientid}',                ['uses' => 'Auth\ConnectedAppsController@getRevokeAppForm']);
Route::post('auth/revokeapp/{clientid}',               ['uses' => 'Auth\ConnectedAppsController@postRevokeAppForm']);

//token inventory management
Route::get('inventory',                                ['as' => 'inventory', 'uses' => 'Inventory\InventoryController@index']);
Route::post('inventory/address/new',                   ['as' => 'inventory.pockets.new', 'uses' => 'Inventory\InventoryController@registerAddress']);
Route::post('inventory/address/{address}/edit',        ['as' => 'inventory.pockets.edit', 'uses' => 'Inventory\InventoryController@editAddress']);
Route::post('inventory/address/{address}/verify',      ['as' => 'inventory.pockets.verify', 'uses' => 'Inventory\InventoryController@verifyAddressOwnership']);
Route::post('inventory/address/{address}/click-verify',['as' => 'inventory.pockets.verify', 'uses' => 'Auth\AuthRegisterController@clickVerifyAddress']);
Route::get('inventory/_check',                         ['as' => 'inventory.check-sig', 'uses' => 'Inventory\InventoryController@checkForVerifySignature']);
Route::get('inventory/address/{address}/delete',       ['as' => 'inventory.pockets.delete', 'uses' => 'Inventory\InventoryController@deleteAddress']);
Route::get('inventory/refresh',                        ['as' => 'inventory.force-update', 'uses' => 'Inventory\InventoryController@refreshBalances']);
Route::get('inventory/check-refresh',                  ['as' => 'inventory.check-refresh', 'uses' => 'Inventory\InventoryController@checkPageRefresh']);
Route::post('inventory/asset/{asset}/toggle',          ['as' => 'inventory.asset.toggle', 'uses' => 'Inventory\InventoryController@toggleAsset']);
Route::get('inventory/lend/{id}/delete',               ['as' => 'inventory.lend.delete', 'uses' => 'Inventory\InventoryController@deleteLoan']);
Route::post('inventory/lend/{id}/edit',                ['as' => 'inventory.lend.delete', 'uses' => 'Inventory\InventoryController@editLoan']);
Route::post('inventory/lend/{address}/{asset}',        ['as' => 'inventory.lend', 'uses' => 'Inventory\InventoryController@lendAsset']);

//token dtails
Route::get('token/{token}',                            ['as' => 'token.info', 'uses' => 'Inventory\InventoryController@getTokenDetails']);


// Image routes
Route::post('image/store',                             ['uses' => 'Image\ImageController@store']);
//Route::post('image/show',                              ['uses' => 'Image\ImageController@show']);


// new route/controller for pockets
Route::get('pockets',                                  ['as' => 'inventory.pockets', 'uses' => 'Inventory\InventoryController@getPockets']);

//client applications / API keys
Route::get('auth/apps',                                ['as' => 'auth.apps', 'uses' => 'Auth\AppsController@index']);
Route::post('auth/apps/new',                           ['uses' => 'Auth\AppsController@registerApp']);
Route::post('auth/apps/{app}/edit',                    ['uses' => 'Auth\AppsController@updateApp']);
Route::patch('auth/apps/{app}/regen',                  ['uses' => 'Auth\AppsController@regenerateApp']);
Route::get('auth/apps/{app}/delete',                   ['uses' => 'Auth\AppsController@deleteApp']);

// -------------------------------------------------------------------------
// User routes

// User routes...
Route::get('dashboard', [
    'as'         => 'user.dashboard',
    'middleware' => ['auth'],
    'uses'       => 'Accounts\DashboardController@getDashboard'
]);



// -------------------------------------------------------------------------
// oAuth routes

// oAuth authorization form...
Route::get('oauth/authorize', [
    'as'         => 'oauth.authorize.get',
    'middleware' => ['check-authorization-params', 'auth',],
    'uses'       => 'OAuth\OAuthController@getAuthorizeForm'
]);
Route::post('oauth/authorize', [
    'as'         => 'oauth.authorize.post',
    'middleware' => ['check-authorization-params', 'auth',],
    'uses'       => 'OAuth\OAuthController@postAuthorizeForm'
]);


