<?php
namespace Tokenpass\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class UserMeta extends Model
{
	protected $table = 'user_meta';
	
	public static function allUser($id)
	{
		$getAll = UserMeta::where('user_id', '=', $id)->get();
		$output = array();
		foreach($getAll as $row){
			$output[$row->meta_key] = $row->meta_value;
		}
		return $output;
	}
	
	public static function getMeta($id, $key, $full = false)
	{
		$get = UserMeta::where('user_id', '=', $id)->where('meta_key', '=', $key)->first();
		if(!$get){
			return false;
		}
        if($full){
            return $get;
        }
		return $get->meta_value;
	}

    public static function getDurationValueHasBeenSet($userId, $value)
    {
        $get = UserMeta::where('user_id', '=', $userId)->where('meta_value', '=', $value)->first();
        $time_diff =  time() - strtotime($get->updated_at);
        return $time_diff;
    }

    public static function getMetaExtraValue($userId, $value)
    {
        $get = UserMeta::where('user_id', '=', $userId)->where('meta_value', '=', $value)->first();
        return $get->extra;
    }

	public static function getAllDataById($id) {
		$get = UserMeta::where('user_id', '=', $id)->get();
		return $get;
	}
	
	public static function setMeta($id, $key, $value, $access_level = 0, $owner_client = 0, $extra = '')
	{
		$get = UserMeta::where('user_id', '=', $id)->where('meta_key', '=', $key)->first();
		if(!$get){
			$get = new UserMeta;
			$get->user_id = $id;
			$get->meta_key = $key;
			$get->access_level = $access_level;
			$get->owner_client = $owner_client;
			$get->extra = $extra;
		}
		$get->meta_value = $value;
        $get->extra = $extra;
		$get->save();
		return true;
	}
    
    public static function clearMeta($id, $key)
    {
        $get = UserMeta::where('user_id', '=', $id)->where('meta_key', '=', $key)->first();
        if(!$get){
            return true;
        }
        $delete = $get->delete();
        if(!$delete){
            return false;
        }
        return true;
    }
	
	
}
