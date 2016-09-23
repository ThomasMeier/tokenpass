<?php

namespace Tokenpass\Console\Commands;

use Illuminate\Console\Command;
use Tokenpass\Models\User;
use Tokenpass\Models\Address;

class ListUserAddresses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokenpass:listAddresses {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gives a list of all registered addresses in users account';

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
        $username = $this->argument('user');
        $all = false;
        if($username == 'ALL'){
            $get_rows = User::all();
            $all = true;
        }
        else{
            $get_rows = User::where('username', $username)->orWhere('confirmed_email', $username)->get();
        }
        if(!$get_rows OR count($get_rows) == 0){
            $this->error('User not found');
            return false;
        }
        $total = 0;
        foreach($get_rows as $get){
            $list = Address::where('user_id', $get->id)->get();
            if(!$list OR count($list) == 0){
                $this->error('No addresses registered for user');
                return false;
            }
        
            foreach($list as $item){
                $text = '';
                if($all){
                    $text = $get->username.' ';
                }
                $text .= '[#'.$item->id.'] '.$item->address;
                if($item->verified == 1){
                    $text .= ' VERIFIED';
                }
                else{
                    $text .= ' *UNVERIFIED*';
                }
                if($item->active_toggle == 0){
                    $text .= ' INACTIVE';
                }
                $this->info($text);
            }
            $total += count($list);
        }
        $this->info('..done ('.$total.')');
    }
}
