<?php

namespace Tokenpass\Models;

use DB, Mail;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use StephenHill\Base58;
use StephenHill\GMPService;
use Tokenly\LaravelApiProvider\Contracts\APIPermissionedUserContract;
use Tokenly\LaravelApiProvider\Model\APIUser;
use Tokenly\LaravelApiProvider\Model\Traits\Permissioned;

class User extends APIUser implements AuthenticatableContract, CanResetPasswordContract, APIPermissionedUserContract
{
    use Notifiable, Authenticatable, CanResetPassword, Permissioned;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'username', 'email', 'password'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    protected $dates = ['confirmation_code_expires_at'];

    protected $casts = [
        'privileges' => 'json',
    ];


    public function updateableFields() {
        return ['name', 'username', 'email', 'password'];
    }

    public function passwordMatches($plaintext_password) {
        return Hash::check($plaintext_password, $this['password']);
    }

    public function emailIsConfirmed() {
        return ($this['confirmed_email'] == $this['email']);
    }

    public function getChannelName() {
        $base58 = new Base58(null, new GMPService());
        return $base58->encode(hash('sha256', $this['uuid'].env('PUBNUB_CHANNEL_SALT'), true));
    }

    public function getChannelAuthKey() {
        $base58 = new Base58(null, new GMPService());
        return $base58->encode(hash('sha256', $this['uuid'].$this['ecc_key'].env('PUBNUB_CHANNEL_SALT')));
    }

    public static function getByVerifiedAddress($data)
    {
        $get_user = DB::table('users')
            ->join('coin_addresses', 'coin_addresses.user_id', '=', 'users.id')
            ->where('coin_addresses.address', '=', $data)
            ->where('coin_addresses.verified', '=', 1)
            ->where('coin_addresses.login_toggle', '=', 1)
            ->where('coin_addresses.active_toggle', '=', 1)
            ->first();

        if(!$get_user) {
            return false;
        }
        return $get_user;
    }
    
    public static function getByOAuth($token)
    {
		$find_sesh = DB::table('oauth_access_tokens')->where('id', $token)->first();
		if(!$find_sesh){
			return false;
		}
		$get_sesh = DB::table('oauth_sessions')->where('id', $find_sesh->session_id)->first();
		if(!$get_sesh OR $get_sesh->owner_type != 'user'){
			return false;
		}
		$get_user = User::find($get_sesh->owner_id);
		if(!$get_user){
			return false;
		}
		return array('user' => $get_user, 'session' => $get_sesh, 'access_token' => $find_sesh);
	}
    
    public static function notifyUser($userId, $view, $subject, $data)
    {
        $user = self::find($userId);
        $data['user'] = $user;
        return Mail::send($view, $data, function($message) use($user, $subject) {
            $message->to($user->email, $user->name)->subject($subject);
        });
    }
    
    public function notify($view, $subject = null, $data = array())
    {
        if(is_object($view)){
            $class = get_class($view);
            switch($class){
                case 'Illuminate\Auth\Notifications\ResetPassword':
                    $token = $view->token;
                    $view = 'emails.password';
                    $subject = 'Tokenpass Password Reset';
                    $data['token'] = $token;
                    break;
                default:
                    return false;
            }
        }
        return self::notifyUser($this->id, $view, $subject, $data);
    }

}
