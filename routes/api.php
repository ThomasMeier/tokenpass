<?php


// -------------------------------------------------------------------------
// API endpoints

// completely unauthenticated
Route::get   ('api/v1/tca/check-address/{address}',            ['as' => 'api.tca.check-address',           'uses' => 'APITCAController@checkAddressTokenAccess']);
Route::post  ('api/v1/instant-verify/{username}',              ['as' => 'api.instant-verify',              'uses' => 'APIController@instantVerifyAddress']);

// requires client_id and signed request
Route::group(['middleware' => 'oauth-client-guard'], function () {

    // get a list of public addresses for a user
    Route::get   ('api/v1/tca/addresses/{username}',           ['as' => 'api.tca.addresses',               'uses' => 'AddressesAPIController@getPublicAddresses']);

    // public address details
    Route::get   ('api/v1/tca/addresses/{username}/{address}', ['as' => 'api.tca.address.public.details',  'uses' => 'AddressesAPIController@getPublicAddressDetails']);

    // lookups
    Route::get   ('api/v1/lookup/address/{address}',               ['as' => 'api.lookup.address',              'uses' => 'APILookupsController@lookupUserByAddress']);
    Route::post  ('api/v1/lookup/addresses',                       ['as' => 'api.lookup.addresses',            'uses' => 'APILookupsController@lookupMultipleUsersByAddresses']);
    Route::get   ('api/v1/lookup/user/{username}',                 ['as' => 'api.lookup.user',                 'uses' => 'APILookupsController@lookupAddressByUser']);

    // provisional transaction routes
    Route::post  ('api/v1/tca/provisional/register',               ['as' => 'api.tca.provisional.register',    'uses' => 'APIProvisionalController@registerProvisionalTCASourceAddress']);
    Route::get   ('api/v1/tca/provisional',                        ['as' => 'api.tca.provisional.list',        'uses' => 'APIProvisionalController@getProvisionalTCASourceAddressList']);
    Route::delete('api/v1/tca/provisional/{address}',              ['as' => 'api.tca.provisional.delete',      'uses' => 'APIProvisionalController@deleteProvisionalTCASourceAddress']);
    Route::get   ('api/v1/tca/provisional/tx',                     ['as' => 'api.tca.provisional.tx.list',     'uses' => 'APIProvisionalController@getProvisionalTCATransactionList']);
    Route::post  ('api/v1/tca/provisional/tx',                     ['as' => 'api.tca.provisional.tx.register', 'uses' => 'APIProvisionalController@registerProvisionalTCATransaction']);
    Route::get   ('api/v1/tca/provisional/tx/{id}',                ['as' => 'api.tca.provisional.tx.get',      'uses' => 'APIProvisionalController@getProvisionalTCATransaction']);
    Route::patch ('api/v1/tca/provisional/tx/{id}',                ['as' => 'api.tca.provisional.tx.update',   'uses' => 'APIProvisionalController@updateProvisionalTCATransaction']);
    Route::delete('api/v1/tca/provisional/tx/{id}',                ['as' => 'api.tca.provisional.tx.delete',   'uses' => 'APIProvisionalController@deleteProvisionalTCATransaction']);
});

Route::group(['middleware' => 'oauth-user-guard'], function () {
    // check and set sign (BTC 2FA)
    Route::get   ('api/v1/tca/check-sign/{address}',               ['as' => 'api.tca.check-sign',              'uses' => 'APIController@checkSignRequirement']);
    Route::post  ('api/v1/tca/set-sign',                           ['as' => 'api.tca.set-sign',                'uses' => 'APIController@setSignRequirement']);

    // invalidate session
    Route::get   ('api/v1/oauth/logout',                           ['as' => 'api.oauth.logout',                'uses' => 'APIController@invalidateOAuth']);
});

Route::group(['middleware' => 'oauth-user-guard:tca'], function () {
    // get a list of all addresses for the user - including inactive and private addresses (if private-address and/or manage-address scope applied)
    Route::get   ('api/v1/tca/addresses',                     ['as' => 'api.tca.private.addresses',       'uses' => 'AddressesAPIController@getPrivateAddresses']);

    // private address details
    Route::get   ('api/v1/tca/address/{address}',              ['as' => 'api.tca.private.address.details', 'uses' => 'AddressesAPIController@getPrivateAddressDetails']);
});

Route::group(['middleware' => 'oauth-user-guard:tca'], function () {
    // check messenger privileges for a token
    Route::get   ('api/v1/tca/messenger/privileges/{token}',   ['as' => 'api.messenger.token.privileges', 'uses' => 'MessengerAPIController@getTokenPrivileges']);

    // Send a message to token holders
    Route::post  ('api/v1/tca/messenger/broadcast',            ['as' => 'api.messenger.broadcast', 'uses' => 'MessengerAPIController@broadcast']);
});

Route::group(['middleware' => 'oauth-user-guard:manage-address'], function () {
    // individual address routes
    Route::post  ('api/v1/tca/address',                        ['as' => 'api.tca.address.new',             'uses' => 'AddressesAPIController@registerAddress']);
    Route::post  ('api/v1/tca/address/{address}',              ['as' => 'api.tca.address.verify',          'uses' => 'AddressesAPIController@verifyAddress']);
    Route::patch ('api/v1/tca/address/{address}',              ['as' => 'api.tca.address.edit',            'uses' => 'AddressesAPIController@editAddress']);
    Route::delete('api/v1/tca/address/{address}',              ['as' => 'api.tca.address.delete',          'uses' => 'AddressesAPIController@deleteAddress']);
});

Route::group(['middleware' => 'oauth-user-guard:tca'], function () {
    // TCA checks
    Route::get   ('api/v1/tca/check/{username}',                   ['as' => 'api.tca.check',                   'uses' => 'APITCAController@checkTokenAccess']);

    // all balances, public only unless private-address or private-balances scope included
    Route::get   ('api/v1/tca/public/balances',             ['as' => 'api.tca.public.balances',      'uses' => 'BalancesAPIController@getPublicBalances']);
});

Route::group(['middleware' => 'oauth-user-guard:private-balances'], function () {
    // all balances, including private balances
    Route::get   ('api/v1/tca/protected/balances',             ['as' => 'api.tca.protected.balances',      'uses' => 'BalancesAPIController@getProtectedBalances']);
});


// oAuth Flow
Route::post  ('api/v1/oauth/request',                          ['as' => 'api.oauth.request',               'uses' => 'APIController@requestOAuth', 'middleware' => ['check-authorization-params']]);
Route::post  ('api/v1/oauth/token',                            ['as' => 'api.oauth.token',                 'uses' => 'APIController@getOAuthToken', 'middleware' => ['check-authorization-params']]);


// deprecated or other api methods

// Route::patch ('api/v1/update',                                 ['as' => 'api.update-account',              'uses' => 'APIController@updateAccount']);
Route::post  ('api/v1/register',                               ['as' => 'api.register',                    'uses' => 'APIController@registerAccount']);
Route::post  ('api/v1/login',                                  ['as' => 'api.login',                       'uses' => 'APIController@loginWithUsernameAndPassword']);


// privileged client endpoint - finds all public and protected usernames/user ids by TCA rules
Route::group(['middleware' => 'api.protectedAuth'], function () {
    Route::get('api/v1/tca/users', ['as' => 'api.tca.usersbytca', 'uses' => 'APITCAController@findUsersByTCARules']);
});
