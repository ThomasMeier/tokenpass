<?php
namespace Tokenpass\Http\Controllers\API;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelApiProvider\Helpers\APIControllerHelper;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;
use Tokenpass\OAuth\Facade\OAuthGuard;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Repositories\AddressRepository;
use Tokenpass\Repositories\UserRepository;

class MessengerAPIController extends Controller
{

    public function __construct(AddressRepository $address_repository, UserRepository $user_repository)
    {
        $this->user_repository    = $user_repository;
        $this->address_repository = $address_repository;
    }


    public function getTokenPrivileges(TCAMessenger $tca_messenger, APIControllerHelper $api_controller_helper, $token)
    {
        $user = OAuthGuard::user();

        $can_message = $tca_messenger->userCanSendMessages($user, $token);

        $response = [
            'token'      => $token,
            'canMessage' => $can_message,
        ];
        return $api_controller_helper->transformValueForOutput($response);
    }

    // public function broadcast(Request $request, TCAMessenger $tca_messenger, APIControllerHelper $api_controller_helper) {
    //     $user = OAuthGuard::user();

    //     $rules = [
    //         'quantity' => 'required|numeric|not_in:0',
    //         'token'    => 'required|token',
    //         'message'  => 'required|max:30000',
    //     ];
    //     $this->validate($request, $rules);
    //     $attributes = $request->only(array_keys($rules));

    //     if (env('DEBUG_ANYONE_CAN_SEND_TOKEN', null) == $attributes['token']) {
    //         Log::debug("DEBUG: allowing anyone to send to token {$attributes['token']}.");
    //     } else if (!$tca_messenger->userCanSendMessages($user, $attributes['token'])) {
    //         return $api_controller_helper->newJsonResponseWithErrors('You are not authorized to send to holders of this token.', 403);
    //     }

    //     $count = $tca_messenger->broadcast($attributes['quantity'], $attributes['token'], $attributes['message']);
    //     return $api_controller_helper->transformValueForOutput([
    //         'success' => true,
    //         'count'   => $count,
    //     ]);
    // }

    // ------------------------------------------------------------------------
    

}
