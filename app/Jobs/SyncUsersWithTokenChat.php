<?php

namespace Tokenpass\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\Models\TokenChat;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;

class SyncUsersWithTokenChat implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $token_chat;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(TokenChat $token_chat)
    {
        $this->token_chat = $token_chat;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(TCAMessenger $tca_messenger)
    {
        EventLog::debug('syncTokenChat.begin', ['id' => $this->token_chat['id'], 'name' => $this->token_chat['name']]);
        $tca_messenger->syncUsersWithChat($this->token_chat);
        EventLog::debug('syncTokenChat.end', ['id' => $this->token_chat['id'], 'name' => $this->token_chat['name']]);
    }
}
