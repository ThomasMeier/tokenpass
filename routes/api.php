<?php


// -------------------------------------------------------------------------
// API endpoints

Route::get   ('api/v1/tca/check/{username}',                   ['as' => 'api.tca.check',                   'uses' => 'APIController@checkTokenAccess']);
Route::get   ('api/v1/tca/check-address/{address}',            ['as' => 'api.tca.check-address',           'uses' => 'APIController@checkAddressTokenAccess']);
Route::get   ('api/v1/tca/check-sign/{address}',               ['as' => 'api.tca.check-sign',              'uses' => 'APIController@checkSignRequirement']);
Route::post  ('api/v1/tca/set-sign',                           ['as' => 'api.tca.set-sign',                'uses' => 'APIController@setSignRequirement']);

Route::get   ('api/v1/tca/addresses/{username}',               ['as' => 'api.tca.addresses',               'uses' => 'APIController@getAddresses']);
Route::get   ('api/v1/tca/addresses/{username}/refresh',       ['as' => 'api.tca.addresses.refresh',       'uses' => 'APIController@getRefreshedAddresses']);
Route::get   ('api/v1/tca/addresses/{username}/{address}',     ['as' => 'api.tca.addresses.details',       'uses' => 'APIController@getAddressDetails']);
Route::post  ('api/v1/tca/addresses/{username}/{address}',     ['as' => 'api.tca.addresses.verify',        'uses' => 'APIController@verifyAddress']);
Route::patch ('api/v1/tca/addresses/{username}/{address}',     ['as' => 'api.tca.addresses.edit',          'uses' => 'APIController@editAddress']);
Route::delete('api/v1/tca/addresses/{username}/{address}',     ['as' => 'api.tca.addresses.delete',        'uses' => 'APIController@deleteAddress']);
Route::post  ('api/v1/tca/addresses',                          ['as' => 'api.tca.addresses.new',           'uses' => 'APIController@registerAddress']);

Route::get   ('api/v1/tca/provisional',                        ['as' => 'api.tca.provisional.list',        'uses' => 'APIController@getProvisionalTCASourceAddressList']);
Route::post  ('api/v1/tca/provisional/register',               ['as' => 'api.tca.provisional.register',    'uses' => 'APIController@registerProvisionalTCASourceAddress']);
Route::get   ('api/v1/tca/provisional/tx',                     ['as' => 'api.tca.provisional.tx.list',     'uses' => 'APIController@getProvisionalTCATransactionList']);
Route::post  ('api/v1/tca/provisional/tx',                     ['as' => 'api.tca.provisional.tx.register', 'uses' => 'APIController@registerProvisionalTCATransaction']);
Route::get   ('api/v1/tca/provisional/tx/{id}',                ['as' => 'api.tca.provisional.tx.get',      'uses' => 'APIController@getProvisionalTCATransaction']);
Route::patch ('api/v1/tca/provisional/tx/{id}',                ['as' => 'api.tca.provisional.tx.update',   'uses' => 'APIController@updateProvisionalTCATransaction']);
Route::delete('api/v1/tca/provisional/tx/{id}',                ['as' => 'api.tca.provisional.tx.delete',   'uses' => 'APIController@deleteProvisionalTCATransaction']);
Route::delete('api/v1/tca/provisional/{address}',              ['as' => 'api.tca.provisional.delete',      'uses' => 'APIController@deleteProvisionalTCASourceAddress']);

Route::post  ('api/v1/oauth/request',                          ['as' => 'api.oauth.request',               'uses' => 'APIController@requestOAuth', 'middleware' => ['check-authorization-params']]);
Route::post  ('api/v1/oauth/token',                            ['as' => 'api.oauth.token',                 'uses' => 'APIController@getOAuthToken', 'middleware' => ['check-authorization-params']]);
Route::get   ('api/v1/oauth/logout',                           ['as' => 'api.oauth.logout',                'uses' => 'APIController@invalidateOAuth']);

Route::patch ('api/v1/update',                                 ['as' => 'api.update-account',              'uses' => 'APIController@updateAccount']);
Route::post  ('api/v1/register',                               ['as' => 'api.register',                    'uses' => 'APIController@registerAccount']);
Route::post  ('api/v1/login',                                  ['as' => 'api.login',                       'uses' => 'APIController@loginWithUsernameAndPassword']);

Route::get   ('api/v1/lookup/address/{address}',               ['as' => 'api.lookup.address',              'uses' => 'APIController@lookupUserByAddress']);
Route::post  ('api/v1/lookup/address/{address}',               ['as' => 'api.lookup.address.post',         'uses' => 'APIController@lookupUserByAddress']);
Route::get   ('api/v1/lookup/user/{username}',                 ['as' => 'api.lookup.user',                 'uses' => 'APIController@lookupAddressByUser']);
Route::post  ('api/v1/instant-verify/{username}',              ['as' => 'api.instant-verify',              'uses' => 'APIController@instantVerifyAddress']);
//Route:get  ('api/v1/oneclick',                               ['as' => 'api.one.click','uses' => 'APIConroller@OneCliCk']);
