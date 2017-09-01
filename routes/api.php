<?php


// -------------------------------------------------------------------------
// API endpoints

// completely unauthenticated
Route::match(['GET',    'OPTIONS'],     'api/v1/tca/check-address/{address}', [
                                        'as'   => 'api.tca.check-address',           
                                        'uses' => 'APITCAController@checkAddressTokenAccess']);
Route::match(['POST',   'OPTIONS'],     'api/v1/instant-verify/{username}', [
                                        'as'   => 'api.instant-verify',              
                                        'uses' => 'APIController@instantVerifyAddress']);


Route::match(['GET',    'OPTIONS'],     'api/v1/perks/{token}', [
                                        'as'   => 'api.token-perks',           
                                        'uses' => 'PerksController@getPerks']);


// requires client_id and signed request
Route::group(['middleware' => 'oauth-client-guard'], function () {

    // get a list of public addresses for a user
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/addresses/{username}', [
                                        'as'   => 'api.tca.addresses',               
                                        'uses' => 'AddressesAPIController@getPublicAddresses']);

    // public address details
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/addresses/{username}/{address}', [
                                        'as'   => 'api.tca.address.public.details',  
                                        'uses' => 'AddressesAPIController@getPublicAddressDetails']);

    // lookups
    Route::match(['GET',    'OPTIONS'], 'api/v1/lookup/address/{address}', [
                                        'as'   => 'api.lookup.address',              
                                        'uses' => 'APILookupsController@lookupUserByAddress']);
    Route::match(['POST',   'OPTIONS'], 'api/v1/lookup/addresses', [
                                        'as'   => 'api.lookup.addresses',            
                                        'uses' => 'APILookupsController@lookupMultipleUsersByAddresses']);
                                        
    Route::match(['GET',    'OPTIONS'], 'api/v1/lookup/user/{username}', [
                                        'as'   => 'api.lookup.user',                 
                                        'uses' => 'APILookupsController@lookupAddressByUser']);
                                        
    Route::match(['GET',    'OPTIONS'], 'api/v1/lookup/email/{email}', [
                                        'as'   => 'api.lookup.email',
                                        'uses' => 'APILookupsController@lookupUserByEmail']); 
                               


    // provisional transaction routes
    Route::match(['POST',   'OPTIONS'], 'api/v1/tca/provisional/register', [
                                        'as'   => 'api.tca.provisional.register',    
                                        'uses' => 'APIProvisionalController@registerProvisionalTCASourceAddress']);
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/provisional', [
                                        'as'   => 'api.tca.provisional.list',        
                                        'uses' => 'APIProvisionalController@getProvisionalTCASourceAddressList']);
    Route::match(['DELETE', 'OPTIONS'], 'api/v1/tca/provisional/{address}', [
                                        'as'   => 'api.tca.provisional.delete',      
                                        'uses' => 'APIProvisionalController@deleteProvisionalTCASourceAddress']);
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/provisional/tx', [
                                        'as'   => 'api.tca.provisional.tx.list',     
                                        'uses' => 'APIProvisionalController@getProvisionalTCATransactionList']);
    Route::match(['POST',            ], 'api/v1/tca/provisional/tx', [
                                        'as'   => 'api.tca.provisional.tx.register', 
                                        'uses' => 'APIProvisionalController@registerProvisionalTCATransaction']);
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/provisional/tx/{id}', [
                                        'as'   => 'api.tca.provisional.tx.get',      
                                        'uses' => 'APIProvisionalController@getProvisionalTCATransaction']);
    Route::match(['PATCH',           ], 'api/v1/tca/provisional/tx/{id}', [
                                        'as'   => 'api.tca.provisional.tx.update',   
                                        'uses' => 'APIProvisionalController@updateProvisionalTCATransaction']);
    Route::match(['DELETE',          ], 'api/v1/tca/provisional/tx/{id}', [
                                        'as'   => 'api.tca.provisional.tx.delete',   
                                        'uses' => 'APIProvisionalController@deleteProvisionalTCATransaction']);
                                        
                                        
    //App Credits API methods

    Route::match(['GET',   'OPTIONS'], 'api/v1/credits', [
                                        'as'   => 'api.credits.list',    
                                        'uses' => 'AppCreditsAPIController@listCreditGroups']);
                                        
    Route::match(['POST',   'OPTIONS'], 'api/v1/credits', [
                                        'as'   => 'api.credits.new',    
                                        'uses' => 'AppCreditsAPIController@newCreditGroup']);            
                                        
                                        
    Route::match(['GET',   'OPTIONS'], 'api/v1/credits/{groupId}', [
                                        'as'   => 'api.credits.details',    
                                        'uses' => 'AppCreditsAPIController@getCreditGroupDetails']);         
                                        
    Route::match(['PATCH',   'OPTIONS'], 'api/v1/credits/{groupId}', [
                                        'as'   => 'api.credits.update',    
                                        'uses' => 'AppCreditsAPIController@updateCreditGroup']);                                                 
                                        
    Route::match(['GET',   'OPTIONS'], 'api/v1/credits/{groupId}/history', [
                                        'as'   => 'api.credits.history',    
                                        'uses' => 'AppCreditsAPIController@getCreditGroupTXHistory']);   
                                        
    Route::match(['GET',   'OPTIONS'], 'api/v1/credits/{groupId}/accounts', [
                                        'as'   => 'api.credits.accounts',    
                                        'uses' => 'AppCreditsAPIController@listCreditAccounts']);   
                                        
    Route::match(['POST',   'OPTIONS'], 'api/v1/credits/{groupId}/accounts', [
                                        'as'   => 'api.credits.accounts.new',    
                                        'uses' => 'AppCreditsAPIController@newCreditAccount']); 
                                        
                                        
    Route::match(['POST',   'OPTIONS'], 'api/v1/credits/{groupId}/accounts/credit', [
                        'as'   => 'api.credits.accounts.credit',    
                        'uses' => 'AppCreditsAPIController@creditAccounts']);                                                                                                                          
         
    Route::match(['POST',   'OPTIONS'], 'api/v1/credits/{groupId}/accounts/debit', [
                        'as'   => 'api.credits.accounts.debit',    
                        'uses' => 'AppCreditsAPIController@debitAccounts']);       
                                                                      
                                        
    Route::match(['GET',   'OPTIONS'], 'api/v1/credits/{groupId}/accounts/{accountId}', [
                                        'as'   => 'api.credits.accounts.details',    
                                        'uses' => 'AppCreditsAPIController@getCreditAccountDetails']);          
                                        
    Route::match(['GET',   'OPTIONS'], 'api/v1/credits/{groupId}/accounts/{accountId}/history', [
                                        'as'   => 'api.credits.accounts.history',    
                                        'uses' => 'AppCreditsAPIController@getCreditAccountTXHistory']);                                                                                                         
       
     
                                                                                                                

});

Route::group(['middleware' => 'oauth-user-guard'], function () {
    // check and set sign (BTC 2FA)
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/check-sign/{address}', [
                                        'as'   => 'api.tca.check-sign',              
                                        'uses' => 'APIController@checkSignRequirement']);
    Route::match(['POST',   'OPTIONS'], 'api/v1/tca/set-sign', [
                                        'as'   => 'api.tca.set-sign',                
                                        'uses' => 'APIController@setSignRequirement']);

    // invalidate session
    Route::match(['GET',    'OPTIONS'], 'api/v1/oauth/logout', [
                                        'as'   => 'api.oauth.logout',                
                                        'uses' => 'APIController@invalidateOAuth']);
});

Route::group(['middleware' => 'oauth-user-guard:tca'], function () {
    // get a list of all addresses for the user - including inactive and private addresses (if private-address and/or manage-address scope applied)
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/addresses', [
                                        'as'   => 'api.tca.private.addresses',       
                                        'uses' => 'AddressesAPIController@getPrivateAddresses']);

    // private address details
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/address/{address}', [
                                        'as'   => 'api.tca.private.address.details', 
                                        'uses' => 'AddressesAPIController@getPrivateAddressDetails']);
});

Route::group(['middleware' => 'oauth-user-guard:chats'], function () {
    // check messenger privileges for a token
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/messenger/privileges/{token}', [
                                        'as'   => 'api.messenger.token.privileges',  
                                        'uses' => 'MessengerAPIController@getTokenPrivileges']);

    // check privilege information for a chat
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/messenger/chat/{chat}', [
                                        'as'   => 'api.messenger.chat.authorization',
                                        'uses' => 'MessengerAPIController@getChatPrivileges']);

    // join a chat
    Route::match(['POST',   'OPTIONS'], 'api/v1/tca/messenger/roster/{chatId}', [
                                        'as'   => 'api.messenger.joinroster',  
                                        'uses' => 'MessengerAPIController@joinChat']);

    // get chats
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/messenger/chats', [
                                        'as'   => 'api.messenger.getchats',  
                                        'uses' => 'MessengerAPIController@getChats']);

});

Route::group(['middleware' => 'oauth-user-guard:manage-chats'], function () {
    // get information about a managed chat
    Route::match(['GET',    'OPTIONS'], 'api/v1/chat/{chatId}', [
                                        'as'   => 'api.messenger.chat.get',  
                                        'uses' => 'MessengerAPIController@getChat']);
    // create a chat
    Route::match(['POST',   'OPTIONS'], 'api/v1/chats', [
                                        'as'   => 'api.messenger.chat.create',  
                                        'uses' => 'MessengerAPIController@createChat']);

    // edit a chat
    Route::match(['POST',   'OPTIONS'], 'api/v1/chat/{chatId}', [
                                        'as'   => 'api.messenger.chat.edit',
                                        'uses' => 'MessengerAPIController@updateChat']);

    // delete a chat
    // TODO
});

Route::group(['middleware' => 'oauth-user-guard:manage-address'], function () {
    // individual address routes
    Route::match(['POST',   'OPTIONS'], 'api/v1/tca/address', [
                                        'as'   => 'api.tca.address.new',             
                                        'uses' => 'AddressesAPIController@registerAddress']);
    Route::match(['POST',   'OPTIONS'], 'api/v1/tca/address/{address}', [
                                        'as'   => 'api.tca.address.verify',          
                                        'uses' => 'AddressesAPIController@verifyAddress']);
    Route::match(['PATCH',           ], 'api/v1/tca/address/{address}', [
                                        'as'   => 'api.tca.address.edit',            
                                        'uses' => 'AddressesAPIController@editAddress']);
    Route::match(['DELETE',          ], 'api/v1/tca/address/{address}', [
                                        'as'   => 'api.tca.address.delete',          
                                        'uses' => 'AddressesAPIController@deleteAddress']);
});

Route::group(['middleware' => 'oauth-user-guard:tca'], function () {
    // TCA checks
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/check/{username}', [
                                        'as'   => 'api.tca.check',                   
                                        'uses' => 'APITCAController@checkTokenAccess']);

    // all balances, public only unless private-address or private-balances scope included
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/public/balances', [
                                        'as'   => 'api.tca.public.balances',         
                                        'uses' => 'BalancesAPIController@getPublicBalances']);
});

Route::group(['middleware' => 'oauth-user-guard:private-balances'], function () {
    // all balances, including private balances
    Route::match(['GET',    'OPTIONS'], 'api/v1/tca/protected/balances', [
                                        'as'   => 'api.tca.protected.balances',      
                                        'uses' => 'BalancesAPIController@getProtectedBalances']);
});


// oAuth Flow
Route::match(['POST',   'OPTIONS'],     'api/v1/oauth/request', [
                                        'as'         => 'api.oauth.request',               
                                        'uses'       => 'APIController@requestOAuth',
                                        'middleware' => ['check-authorization-params']]);
Route::match(['POST',   'OPTIONS'],     'api/v1/oauth/token', [
                                        'as'         => 'api.oauth.token',                 
                                        'uses'       => 'APIController@getOAuthToken',
                                        'middleware' => ['check-authorization-params']]);


// deprecated or other api methods

// Route::match(['PATCH',  'OPTIONS'],  'api/v1/update', [
//                                      'as'   => 'api.update-account',              
//                                      'uses' => 'APIController@updateAccount']);
Route::match(['POST',   'OPTIONS'],     'api/v1/register', [
                                        'as'   => 'api.register',                    
                                        'uses' => 'APIController@registerAccount']);
Route::match(['POST',   'OPTIONS'],     'api/v1/login', [
                                        'as'   => 'api.login',                       
                                        'uses' => 'APIController@loginWithUsernameAndPassword']);


// privileged client endpoints
Route::group(['middleware' => 'api.protectedAuth'], function () {
    // finds all usernames and user ids by TCA rules - uses public and protected balances
    Route::match(['GET', 'OPTIONS'],    'api/v1/tca/users', [
                                        'as'   => 'api.tca.usersbytca',              
                                        'uses' => 'APITCAController@findUsersByTCARules']);

    // determine if a user exists
    Route::match(['GET', 'OPTIONS'],    'api/v1/lookup/user/exists/{username}', [
                                        'as'   => 'api.lookup.user.check-exists',
                                        'uses' => 'APILookupsController@checkUserExists']);

         

});
