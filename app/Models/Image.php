<?php
/**
 * Created by PhpStorm.
 * User: one
 * Date: 03/07/16
 * Time: 18:50
 */


namespace Tokenpass\Models;

use Aws\S3\S3Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use League\Flysystem\Filesystem as Flysystem;
use Illuminate\Http\Response;
use Intervention\Image\ImageManagerStatic as ImageManager;

class Image extends Model {

    protected $imageFileName;
    
    public static function store($request) {

        $filesystem = Image::getS3Client();
        $response_code = 200;

        $image = $request->allFiles();
        $imageFileName = hash('sha256', Facades\Auth::user()->getUuid());
        $extension = $image['file']->getClientOriginalExtension();

        try {
            $filesystem->deleteDir($imageFileName);
        } catch(Exception $e) {
            //EventLog::info('No file to delete at ' . $imageFileName);
        }

        try {
            $stream = fopen($image['file'], 'r+');
            $result = $filesystem->writeStream($imageFileName . '/' . $imageFileName . '.' . $extension, $stream);
            @fclose($stream);
            Image::buildAvatar($image['file']);
        } catch(\Aws\CloudFront\Exception\Exception $e) {
            $response_code = 500;
            $result = false;
            //EventLog::info('No file to write of name ' . $imageFileName);
        }

        return [
            'message' => $result,
            'status' => $response_code];
    }

    public static function show($path = null, $avatar = true) {

        // Use for private access files, not require yet.
        $filesystem = Image::getS3Client();
        $response_code = 200;
        if(is_null($path)) {
            $path = hash('sha256', Facades\Auth::user()->getUuid());
        }

        return $filesystem->get($path);
    }

    private static function storeAvatar($image) {
        $filesystem = Image::getS3Client();
        $imageFileName = hash('sha256', Facades\Auth::user()->getUuid());

        try {
            $stream = fopen($image, 'r+');
            $result = $filesystem->writeStream($imageFileName . '/' . 'avatar' . '.png', $stream);

        } catch(\Aws\CloudFront\Exception\Exception $e) {
            $response_code = 500;
            $result = false;
            //EventLog::info('No file to write of name ' . $imageFileName);
        }
    }

    private static function buildAvatar($image) {
        ImageManager::make($image)->resize(50, 50)
            ->encode('png')
            ->save(Image::storeAvatar($image));
    }

    private static function getS3Client() {
        $client = S3Client::factory([
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ]);

        $adapter = new AwsS3Adapter($client, env('S3_BUCKET'));
        $filesystem = new Flysystem($adapter);

        return $filesystem;
    }
}
