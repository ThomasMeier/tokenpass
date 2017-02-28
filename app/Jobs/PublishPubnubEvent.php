<?php

namespace Tokenpass\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\Models\User;
use Tokenpass\Providers\TCAMessenger\TCAMessengerActions;
use Exception;

class PublishPubnubEvent implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $event_name;
    protected $args;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, $event_name, $args=[])
    {
        $this->user       = $user;
        $this->event_name = $event_name;
        $this->args       = $args;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(TCAMessengerActions $messenger)
    {
        try {
            call_user_func([$messenger, $this->event_name], $this->user, $this->args);
        } catch (Exception $e) {
            EventLog::logError('publishEvent', $e, ['user' => $this->user['id'], 'action' => $this->event_name]);
            throw $e;
        }
        EventLog::debug('publishEvent', ['user' => $this->user['id'], 'action' => $this->event_name]);
    }
}
