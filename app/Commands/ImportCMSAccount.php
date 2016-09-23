<?php

namespace Tokenpass\Commands;

use Carbon\Carbon;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tokenpass\Commands\Command;
use Tokenpass\Commands\SendUserConfirmationEmail;
use Tokenpass\Commands\SyncCMSAccount;
use Tokenpass\Models\User;
use Tokenpass\Providers\CMSAuth\Util;
use Illuminate\Foundation\Bus\DispatchesJobs;

class ImportCMSAccount extends Command implements SelfHandling
{

    use DispatchesJobs;


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $user_repository = App('Tokenpass\Repositories\UserRepository');

        $existing_user = $user_repository->findBySlug(Util::slugify($this->username));
        if ($existing_user) { throw new \Exception('Invalid credentials'); }

        // load user info from CMS
        $loader = app('Tokenpass\Providers\CMSAuth\CMSAccountLoader');

        // get the full user info
        $cms_user_info = $loader->getFullUserInfoWithLogin($this->username, $this->password);
        if (!$cms_user_info) {
			throw new \Exception('Invalid credentials');	
		}

        // create a new user account
        $user_vars = [
            'username' => $this->username,
            'password' => $this->password,
            'email' => '',
        ];

        // email
        if (isset($cms_user_info['email']) AND strlen($cms_user_info['email'])) {
            $user_vars['email'] = $cms_user_info['email'];
        }
        
        if(trim($user_vars['email']) == ''){
			throw new \Exception('Email required');
		}
        
        $email_used = User::where('email', $user_vars['email'])->first();
        if($email_used){
			throw new \Exception('Email already in use');
		}

        // real name
        if (isset($cms_user_info['profile']) AND $cms_user_info['profile'] AND isset($cms_user_info['profile']['real-name']) AND strlen($cms_user_info['profile']['real-name']['value'])) {
            $user_vars['name'] = $cms_user_info['profile']['real-name']['value'];
        }

        $new_user = $user_repository->create($user_vars);
        
        //attempt to load in any BTC addresses and other relevant account data they may have
        $this->dispatch(new SyncCMSAccount($new_user, array('username' => $this->username, 'password' => $this->password)));

        // and send welcome email
        $this->dispatch(new SendUserConfirmationEmail($new_user));

        return true;
    }



}
