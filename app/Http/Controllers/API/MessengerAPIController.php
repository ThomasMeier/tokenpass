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

    public function createChat(Request $request, TCAMessenger $tca_messenger, TokenChatRepository $token_chat_repository, APIControllerHelper $api_controller_helper) {
        $user = OAuthGuard::user();

        $input = $this->validateChatAttributes($request, $api_controller_helper, [
            'name'      => 'required',
            'active'    => 'sometimes|boolean',
            'global'    => 'sometimes|boolean',
            'tca_rules' => 'sometimes|max:2048',
        ]);

        // create the chat
        $new_chat = $token_chat_repository->create(array_merge($input, [
            'user_id'   => $user['id'],
        ]));

        // update the chat
        $tca_messenger->onChatLifecycle($new_chat);

        // return the chat
        return $api_controller_helper->transformResourceForOutput($new_chat);
    }

    public function updateChat($uuid, Request $request, TCAMessenger $tca_messenger, TokenChatRepository $token_chat_repository, APIControllerHelper $api_controller_helper) {
        $user = OAuthGuard::user();
        $token_chat = $api_controller_helper->requireResourceOwnedByUser($uuid, $user, $token_chat_repository);

        // validate
        $input = $this->validateChatAttributes($request, $api_controller_helper, [
            'active'    => 'sometimes|boolean',
            'global'    => 'sometimes|boolean',
            'tca_rules' => 'sometimes|max:2048',
        ]);

        // edit the chat
        $token_chat_repository->update($token_chat, $input);

        // update the chat
        $tca_messenger->onChatLifecycle($token_chat);

        // return the chat
        return $api_controller_helper->transformResourceForOutput($token_chat);
    }

    // public function deleteChat($uuid, Request $request, TokenChatRepository $token_chat_repository, APIControllerHelper $api_controller_helper) {
    //     $user = OAuthGuard::user();
    //     $token_chat = $api_controller_helper->requireResourceOwnedByUser($uuid, $user, $token_chat_repository);

    //     // delete the chat
    //     $token_chat_repository->delete($token_chat);

    //     // update the privileges
    //     $tca_messenger->onChatDeleted($token_chat);

    //     // return nothing
    //     return $api_controller_helper->transformValueForOutput([]);
    // }

    public function getChat($uuid, Request $request, TokenChatRepository $token_chat_repository, APIControllerHelper $api_controller_helper) {
        $user = OAuthGuard::user();
        $token_chat = $api_controller_helper->requireResourceOwnedByUser($uuid, $user, $token_chat_repository);

        // return the chat
        return $api_controller_helper->transformResourceForOutput($token_chat);
    }

    // ------------------------------------------------------------------------

    protected function validateChatAttributes(Request $request, APIControllerHelper $api_controller_helper, $validation_rules) {
        $tca_messenger = app(TCAMessenger::class);

        $this->validate($request, $validation_rules);
        $attributes = $request->only(array_keys($validation_rules));

        $is_global = false;
        if (isset($attributes['global'])) {
            $is_global = $attributes['global'];
            if ($is_global) {
                $api_controller_helper->requirePermission($user, 'globalChats', 'create global chats');
            }
        }
        $attributes['global'] = $is_global;

        try {
            $tca_rules = [];
            if (!$is_global AND isset($attributes['tca_rules'])) {
                $tca_rules = $tca_messenger->makeSimpleTCAStackFromSerializedInput(is_array($attributes['tca_rules']) ? $attributes['tca_rules'] : json_decode($attributes['tca_rules'], true));
            }
            $attributes['tca_rules'] = $tca_rules;
            if (!$is_global AND !$tca_rules) {
                throw $api_controller_helper->buildJSONResponseException('Non-global chats require one or more access tokens', 422);
            }
        } catch (InvalidArgumentException $e) {
            throw $api_controller_helper->buildJSONResponseException($e->getMessage(), 422);
        }

        // remove null attributes
        $filtered_attributes = [];
        foreach (array_keys($attributes) as $key) {
            if (!is_null($attributes[$key])) {
                $filtered_attributes[$key] = $attributes[$key];
            }
        }
        return $filtered_attributes;
    }

}
