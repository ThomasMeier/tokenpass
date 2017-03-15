<?php
namespace Tokenpass\Http\Controllers\API;
use Exception;
use Tokenly\AssetNameUtils\Validator;
use Tokenly\LaravelApiProvider\Helpers\APIControllerHelper;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\User;
use Tokenpass\Repositories\TokenChatRepository;
use Tokenpass\Util\BitcoinUtil;

class PerksController extends Controller
{

    public function getPerks($token, TokenChatRepository $token_chat_repository, APIControllerHelper $api_controller_helper)
    {
        if (!Validator::isValidAssetName($token)) {
            return $api_controller_helper->newJsonResponseWithErrors('Invalid token name', 422);
        }

        $response = ['token' => $token,];

        // get number of active chats
        $chat_count = $token_chat_repository->getTokenChatsCountByAsset($token, true);
        $response['chatCount'] = $chat_count;

        return $api_controller_helper->buildJSONResponse($response);
   }

}
