<?php

namespace Tokenpass\Console\Commands\Messenger;

use Illuminate\Console\Command;
use Tokenpass\Repositories\TokenChatRepository;

class ResyncChat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messenger:resync-chat 
                            {chatId : UUID of the Token Chat}
                            {--f|full : Do a full re-authorization}';

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
        $chat_id = $this->argument('chatId');
        $do_full_reauthorization = $this->option('full');
        $token_chat_repository = app(TokenChatRepository::class);
        $token_chat = $token_chat_repository->findByUuid($chat_id);
        if (!$token_chat) {
            $token_chat = $token_chat_repository->findById($chat_id);
        }
        if (!$token_chat) {
            $this->error("Chat not found for id $chat_id");
            return;
        }

        if ($do_full_reauthorization) {
            $this->comment('Re-authorizing chat '.$token_chat['name'].' ('.$token_chat['uuid'].')');
            app('Tokenpass\Providers\TCAMessenger\TCAMessenger')->authorizeChat($token_chat);

        } else {
            $this->comment('Syncing chat '.$token_chat['name'].' ('.$token_chat['uuid'].')');
            app('Tokenpass\Providers\TCAMessenger\TCAMessenger')->syncUsersWithChat($token_chat);
            
        }

        $this->comment('done');
    }
}
