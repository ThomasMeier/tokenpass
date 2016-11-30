<?php

namespace Tokenpass\Console\Commands\Messenger;

use Illuminate\Console\Command;
use Tokenpass\Repositories\TokenChatRepository;

class ShowChats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messenger:show-chats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List chats';

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
        $token_chat_repository = app(TokenChatRepository::class);
        $token_chats = $token_chat_repository->findAll();

        $bool = function($val) { return $val ? '<info>true</info>' : '<comment>false</comment>'; };
        $headers = ['id','uuid','name','user','active',];
        $rows = [];
        foreach($token_chats as $token_chat) {
            $rows[] = [
                $token_chat['id'],
                $token_chat['uuid'],
                $token_chat['name'],
                $token_chat->user['username'],
                $bool($token_chat['active']),
            ];
        }

        $this->table($headers, $rows);
    }
}
