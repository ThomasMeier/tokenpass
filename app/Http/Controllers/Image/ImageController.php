<?php
/**
 * Created by PhpStorm.
 * User: one
 * Date: 04/07/16
 * Time: 12:05
 */

namespace Tokenpass\Http\Controllers\Image;

use Aws\S3\S3Client;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Logging\Log;
use Illuminate\Contracts\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Log\Writer;
use Illuminate\Support\Facades;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use Mockery\CountValidator\Exception;
use Tokenpass\Repositories\ImageRepository;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Image;
use Tokenly\LaravelEventLog\EventLog;


class ImageController extends Controller {

    public function store(Request $request){
        
        if($request->file('file') == null){
            return response()->json('Only image type files are accepted as an avatar.', 400);
        }
        $type = substr($request->file('file')->getClientMimeType(), 0, 5);
        try {
            if ($type == 'image') {
                $result = Image::store($request);
            } else {
                return response()->json('Only image type files are accepted as an avatar.', 400);
            }
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 400);
        }

        if (!$result OR !$result['message']) {
            return response()->json('There has been an error', 400);
        } else {
            return response()->json('Avatar defined.');
        }
    }

    /**
     * Get the failed message.
     *
     * @return string
     */
    protected function getGenericFailedMessage()
    {
        return Lang::has('auth.generic.fail')
            ? Lang::get('auth.generic.fail')
            : 'There has been an error, please check your input.';
    }

}
