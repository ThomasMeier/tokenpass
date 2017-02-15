<?php

namespace Tokenpass\Console\Commands\Messenger;

use Illuminate\Console\Command;
use Tokenly\TCA\Access;
use Tokenpass\Models\Address;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use Tokenpass\Repositories\TokenChatRepository;
use Tokenpass\Repositories\UserRepository;

class AnalyzeChatPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messenger:analyze-permissions 
                            {userId : UUID or username of the User}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-syncs permissions for a chat';

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

        $user_repository = app(UserRepository::class);
        $user = $user_repository->findByUuid($user_id);
        if (!$user) {
            $user = $user_repository->findByUsername($user_id);
        }
        if (!$user) {
            $this->error("User not found for id $user_id");
            return;
        }

        $bool = function($val) { return $val ? '<info>true</info>' : '<comment>false</comment>'; };
        $headers = ['chat','uuid','should_be_authorized','is_authorized',];
        $rows = [];

        $tca_messenger = app(TCAMessenger::class);
        $tca_messenger_auth = app(TCAMessengerAuth::class);
        $token_chat_repository = app(TokenChatRepository::class);
        foreach($token_chat_repository->findAll() as $token_chat) {
            $is_authorized = false;
            if ($token_chat['active']) {
                if ($token_chat['global']) {
                    $is_authorized = true;
                } else {
                    $tca = new Access();
                    $is_authorized = $this->userIDIsAuthorized($user['id'], $token_chat['tca_rules']);
                }
            }

            $channel_name = $token_chat->getChannelName();
            $chat_channel = "chat-{$channel_name}";
            $user_is_already_authorized = $tca_messenger_auth->userIsAuthorized($user['id'], $chat_channel);

            $rows[] = [
                $token_chat['name'],
                $token_chat['uuid'],
                $bool($is_authorized),
                $bool($user_is_already_authorized),
            ];
        }

        $this->line('Showing access for user '.$user['username'].' ('.$user['name'].', '.$user['uuid'].')');
            
        $this->table($headers, $rows);

        $this->comment('done');
    }


    protected function userIDIsAuthorized($user_id, $tca_stack) {
        $tca = new Access();
        $balances = Address::getAllUserBalances($user_id, $filter_disabled = true, $and_provisional = true, $subtract_loans = true);
        return $tca->checkAccess($tca_stack, $balances);
    }

}
