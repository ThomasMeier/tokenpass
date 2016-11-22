<?php
namespace Tokenpass\Http\Controllers\API;
use Exception;
use Tokenly\BvamApiClient\BVAMClient;
use Tokenly\LaravelApiProvider\Helpers\APIControllerHelper;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;
use Tokenpass\OAuth\Facade\OAuthGuard;
use Tokenpass\Repositories\AddressRepository;
use Tokenpass\Repositories\UserRepository;

class MessengerAPIController extends Controller
{

    public function __construct(BVAMClient $bvam_client, AddressRepository $address_repository, UserRepository $user_repository)
    {
        $this->bvam_client      = $bvam_client;
        $this->user_repository    = $user_repository;
        $this->address_repository = $address_repository;
    }


    public function getTokenPrivileges(APIControllerHelper $api_controller_helper, $token)
    {
        $user = OAuthGuard::user();

        $can_message = $this->userCanSendMessages($user, $token);

        $response = [
            'token'      => $token,
            'canMessage' => $can_message,
        ];
        return $api_controller_helper->transformValueForOutput($response);
    }

    // ------------------------------------------------------------------------
    
    protected function userCanSendMessages(User $user, $token) {
        // get issuer address
        $bvam = $this->bvam_client->getAssetInfo($token);
        $issuer_address = $bvam['assetInfo']['issuer'];

        $all_addresses_DEBUG = Address::all();

        // get all authenticated addresses for this user
        $addresses = Address::getAddressList($user['id'], $public=null, $active=null, $verified=true);

        foreach($addresses as $address) {
            if ($address['address'] == $issuer_address) {
                return true;
            }
        }
        return false;
    }

}
