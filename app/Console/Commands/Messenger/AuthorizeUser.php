<?php

namespace Tokenpass\Console\Commands\Messenger;

use Illuminate\Console\Command;
use Tokenpass\Repositories\TokenChatRepository;
use Tokenpass\Repositories\UserRepository;

class AuthorizeUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messenger:authorize-user 
                            {userId : UUID of the User}
                            {chatId : UUID of the Token Chat}
                            {--d|deauthorize : Deauthorize instead of authorize}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Authorize/Deauthorize user from a chat';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->comment('begin');
        $user_id     = $this->argument('userId');
        $chat_id     = $this->argument('chatId');
        $deauthorize = $this->option('deauthorize');

        $user_repository = app(UserRepository::class);
        $user = $user_repository->findByUuid($user_id);
        if (!$user) {
            $user = $user_repository->findById($user_id);
        }
        if (!$user) {
            $this->error("User not found for id $user_id");
            return;
        }

        $token_chat_repository = app(TokenChatRepository::class);
        $token_chat = $token_chat_repository->findByUuid($chat_id);
        if (!$token_chat) {
            $token_chat = $token_chat_repository->findById($chat_id);
        }
        if (!$token_chat) {
            $this->error("Chat not found for id $chat_id");
            return;
        }

        if ($deauthorize) {
            $this->comment('Deauthorizing user '.$user['name'].' ('.$user['uuid'].') from chat '.$token_chat['name'].' ('.$token_chat['uuid'].')');
            app('Tokenpass\Providers\TCAMessenger\TCAMessenger')->deauthorizeUserFromChat($user, $token_chat);

        } else {
            $this->comment('Authorizing user '.$user['name'].' ('.$user['uuid'].') to chat '.$token_chat['name'].' ('.$token_chat['uuid'].')');
            app('Tokenpass\Providers\TCAMessenger\TCAMessenger')->authorizeUserToChat($user, $token_chat);
            
        }

        $this->comment('done');
    }
}
