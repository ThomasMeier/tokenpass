<?php

namespace Tokenpass\Console\Commands\MessengerDev;

use Illuminate\Console\Command;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Repositories\TokenChatRepository;

class ResendChatOwnerIdentities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev-messenger:resend-owner-identities 
                            {chatId : UUID of the Token Chat or ALL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-sends all owner identities for token chats';

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
        $chat_id = $this->argument('chatId');
        $token_chat_repository = app(TokenChatRepository::class);

        $token_chats = [];
        if (strtolower($chat_id) == 'all') {
            $token_chats = $token_chat_repository->findAll();
        } else {
            $token_chat = $token_chat_repository->findByUuid($chat_id);
            if (!$token_chat) {
                $token_chat = $token_chat_repository->findById($chat_id);
            }
            if (!$token_chat) {
                $this->error("Chat not found for id $chat_id");
                return;
            }
            
            $token_chats[] = $token_chat;
        }

        $tca_messenger = app(TCAMessenger::class);
        foreach($token_chats as $token_chat) {
            $this->comment('Resending owner identity for chat '.$token_chat['name'].' ('.$token_chat['uuid'].')');
            $tca_messenger->addUserToChat($token_chat->user, $token_chat, true);
        }

        $this->comment('done');
    }
}
