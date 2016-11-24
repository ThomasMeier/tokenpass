<?php

namespace Tokenpass\Http\Controllers\Tokenchats;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as CurrentRequest;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Repositories\TokenChatRepository;

class TokenchatsController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    
    public function index(TokenChatRepository $token_chat_repository)
    {
        $user = Auth::user();

		return view('tokenchats.index', [
            'chats' => $token_chat_repository->findAllByUser($user),
		]);
		
	}

    public function create(Request $request, TokenChatRepository $token_chat_repository, TCAMessenger $tca_messenger) {

        try {
            $this->validate($request, [
                'name'     => 'required|max:255',
                'quantity' => 'required|numeric|not_in:0',
                'token'    => 'required|token',
            ]);
        } catch (ValidationException $e) {
            return $this->returnInvalidResponse($e);
        }

        $input = $request->input();

        // create a new chat
        $tca_rules = $tca_messenger->makeSimpleTCAStack($input['quantity'], $input['token']);
        $token_chat_repository->create([
            'user_id'   => Auth::id(),
            'name'      => $input['name'],
            'tca_rules' => $tca_rules,
            'active'    => true,
        ]);

        return $this->ajaxEnabledSuccessResponse('New Token Chat created.', route('tokenchats.index'));
    }

    public function edit($uuid, Request $request, TokenChatRepository $token_chat_repository, TCAMessenger $tca_messenger) {
        $user = Auth::user();
        $chat_model = $token_chat_repository->findByUuid($uuid);
        if (!$chat_model) {
            return $this->ajaxEnabledErrorResponse('Chat not found', route('tokenchats.index'));
        }
        if ($chat_model['user_id'] != $user['id']) {
            return $this->ajaxEnabledErrorResponse('This chat does not belong to you', route('tokenchats.index'), 403);
        }

        try {
            $this->validate($request, [
                'name'     => 'required|max:255',
                'quantity' => 'required|numeric|not_in:0',
                'token'    => 'required|token',
                'active'   => 'required|boolean',
            ]);
        } catch (ValidationException $e) {
            return $this->returnInvalidResponse($e);
        }

        $input = $request->input();

        // create a new chat
        $tca_rules = $tca_messenger->makeSimpleTCAStack($input['quantity'], $input['token']);
        $token_chat_repository->update($chat_model, [
            'name'      => $input['name'],
            'tca_rules' => $tca_rules,
            'active'    => $input['active'],
        ]);

        return $this->ajaxEnabledSuccessResponse('Token Chat updated.', route('tokenchats.index'));
    }

    public function destroy($uuid, Request $request, TokenChatRepository $token_chat_repository, TCAMessenger $tca_messenger) {
        $user = Auth::user();
        $chat_model = $token_chat_repository->findByUuid($uuid);
        if (!$chat_model) {
            return $this->ajaxEnabledErrorResponse('Chat not found', route('tokenchats.index'));
        }
        if ($chat_model['user_id'] != $user['id']) {
            return $this->ajaxEnabledErrorResponse('This chat does not belong to you', route('tokenchats.index'), 403);
        }

        $token_chat_repository->delete($chat_model);

        return $this->ajaxEnabledSuccessResponse('Token Chat deleted.', route('tokenchats.index'));
    }

    // ------------------------------------------------------------------------

    protected function returnInvalidResponse(ValidationException $e) {
        $messages = $e->validator->messages();

        $all_error_messages = [];
        foreach($messages->messages() as $message_texts) {
            foreach($message_texts as $message_text) {
                $all_error_messages[] = $message_text;
            }
        }

        return $this->ajaxEnabledErrorResponse(implode('<br />', $all_error_messages), route('tokenchats.index'));
    }

    protected function ajaxEnabledErrorResponse($error_message, $redirect_url, $error_code = 400) {
        if (CurrentRequest::ajax()) {
            return Response::json(['success' => false, 'error' => $error_message], $error_code);
        }

        Session::flash('message', $error_message);
        Session::flash('message-class', 'alert-danger');
        return redirect($redirect_url);
    }

    protected function ajaxEnabledSuccessResponse($success_message, $redirect_url, $http_code = 200) {
        if (CurrentRequest::ajax()) {
            return Response::json([
                'success'     => true,
                'message'     => $success_message,
                'redirectUrl' => $redirect_url,
            ], $http_code);
        }

        Session::flash('message', $success_message);
        Session::flash('message-class', 'alert-success');


        return redirect(route('tokenchats.index'));
    }
    
}
