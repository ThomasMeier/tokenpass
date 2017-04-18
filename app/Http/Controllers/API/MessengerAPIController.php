<?php
namespace Tokenpass\Http\Controllers\API;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelApiProvider\Helpers\APIControllerHelper;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\Models\TokenChat;
use Tokenpass\Models\User;
use Tokenpass\OAuth\Facade\OAuthGuard;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use Tokenpass\Repositories\AddressRepository;
use Tokenpass\Repositories\TokenChatRepository;
use Tokenpass\Repositories\UserRepository;

class MessengerAPIController extends Controller
{

    public function __construct(AddressRepository $address_repository, UserRepository $user_repository)
    {
        $this->user_repository    = $user_repository;
        $this->address_repository = $address_repository;
    }


    public function getChats(TCAMessenger $tca_messenger, TokenChatRepository $token_chat_repository, APIControllerHelper $api_controller_helper) {
        $user = OAuthGuard::user();

        $chat_models = $token_chat_repository->findAllByUser($user);
        return $api_controller_helper->transformResourcesForOutput($chat_models);
    }

    public function getChatPrivileges(TCAMessenger $tca_messenger, TokenChatRepository $token_chat_repository, APIControllerHelper $api_controller_helper, $chat_id)
    {
        $user = OAuthGuard::user();

        $uuid = TokenChat::channelNameToUuid($chat_id);
        $token_chat = $token_chat_repository->findByUuid($uuid);
        if (!$token_chat) {
            return $api_controller_helper->newJsonResponseWithErrors('Chat not found', 404);
        }

        $token_auth = $tca_messenger->userAuthorizationInformationForTokenChat($user, $token_chat);

        $response = [
            'authorized'         => $tca_messenger->userIsAuthorized($user, $token_chat),
            'isGlobal'           => $token_chat['global'],
            'tokenAuthorization' => $token_auth,
        ];

        return $api_controller_helper->transformValueForOutput($response);
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

    public function joinChat($chat_id, TokenChatRepository $token_chat_repository, TCAMessenger $tca_messenger, TCAMessengerAuth $tca_messenger_auth, APIControllerHelper $api_controller_helper)
    {
        $user = OAuthGuard::user();

        $uuid = TokenChat::channelNameToUuid($chat_id);
        $token_chat = $token_chat_repository->findByUuid($uuid);
        if (!$token_chat) {
            return $api_controller_helper->newJsonResponseWithErrors('Chat not found', 404);
        }

        $channel_name = $token_chat->getChannelName();
        $chat_channel = "chat-{$channel_name}";
        if (!$tca_messenger_auth->userIsAuthorized($user['id'], $chat_channel)) {
            return $api_controller_helper->newJsonResponseWithErrors('Not authorized for this chat', 403);
        }

        $tca_messenger->addUserToChat($user, $token_chat);

        $response = [
            'success' => true,
        ];
        return $api_controller_helper->transformValueForOutput($response);
    }

}
