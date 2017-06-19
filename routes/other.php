<?php


// ------------------------------------------------------------------------
// OAuth API-like routes

// oAuth access token
Route::post('oauth/access-token', [
    'as'   => 'oauth.accesstoken',
    'uses' => 'OAuth\OAuthController@postAccessToken'
]);

// oAuth user
Route::get('oauth/user', [
    'as'         => 'oauth.user',
    'middleware' => ['oauth',],
    'uses'       => 'OAuth\OAuthController@getUser'
]);


// ------------------------------------------------------------------------
// webhook notifications

Route::post('_xchain_client_receive', ['as' => 'xchain.receive', 'uses' => 'XChain\XChainWebhookController@receive']);

//Verify ownership through payment webhook
Route::post('inventory/address/verify_payment', ['as' => 'inventory.pockets.verify_payment', 'uses' => 'Inventory\InventoryController@receiveVerifyPayment']);