<?php

namespace Tokenpass\Console\Commands\MessengerDev;

use Illuminate\Console\Command;
use Tokenpass\Models\TokenChat;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use Tokenpass\Repositories\TokenChatRepository;

class DeleteChatByChannel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev-messenger:delete-chat 
                            {channelId : Channel ID of the Token Chat}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes a chat';

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
        $channel_id = $this->argument('channelId');
        $token_chat = app(TokenChat::class);
        $token_chat->forceChannelName($channel_id);
        
        $chat_channel = "chat-".$token_chat->getChannelName();

        $auth = app(TCAMessengerAuth::class);
        $user_ids = $auth->findUserIDsByChannel($chat_channel)->pluck('user_id');
        $this->comment('For channel '.$chat_channel.', found '.count($user_ids).' '.str_plural('user', count($user_ids)).'.');
        foreach($user_ids as $user_id) {
            $user = $user_repository->findById($user_id);
            $this->deauthorizeUserFromChat($user, $token_chat);
        }

        $this->comment('done');
    }
}
