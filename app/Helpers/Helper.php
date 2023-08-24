<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Azimo\Apple\Auth\Factory\AppleJwtStructFactory;
use Azimo\Apple\Auth\Jwt\JwtParser;
use Azimo\Apple\Auth\Jwt\JwtValidator;
use Azimo\Apple\Auth\Jwt\JwtVerifier;
use Azimo\Apple\Auth\Service\AppleJwtFetchingService;
use Azimo\Apple\Api\AppleApiClient;
use Azimo\Apple\Api\Factory\ResponseFactory;
use Azimo\Apple\Auth\Exception\ValidationFailedException;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use App\Models\ErrorLogs;
use App\Models\User;
use App\Models\Country;
use Carbon\Carbon;

/**
 * Create slug from string.
 *
 * @param  $title
 * @param  $slug this param used only if we let user enter slug in the form
 * @param  $model_name
 * @param  $object_id this param used in update model
 * @return $slug
 */

function getUserImg($img)
{
    $domain = $_SERVER['HTTP_HOST'];
    $checkImg = Str::substr($img, 0, 4);
    $checkImg = Str::lower($checkImg);
    // check img source
    if ($checkImg == 'http') return $img;  // get img by social med
    elseif (!empty($img)) return $domain . $img;  // get img by database

    return null;
}

function getUserIpAddress()
{
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"]))
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    return null;
}

function retrieveJwtPayload(string $jwt, string $source = null)
{
    $client_id = Str::is($source, 'website') ? 'com.reactapp.signin' : 'com.reactapp.ios';

    try {
        $appleJwtFetchingService = new AppleJwtFetchingService(
            new JwtParser(new Parser(new JoseEncoder())),
            new JwtVerifier(
                new AppleApiClient(
                    new Client(
                        [
                            'base_uri' => 'https://appleid.apple.com',
                            'timeout' => 5,
                            'connect_timeout' => 5,
                        ]
                    ),
                    new ResponseFactory()
                ),
                new \Lcobucci\JWT\Validation\Validator(),
                new Sha256()
            ),
            new JwtValidator(
                new \Lcobucci\JWT\Validation\Validator(),
                [
                    new IssuedBy('https://appleid.apple.com'),
                    new PermittedFor($client_id),
                ]
            ),
            new AppleJwtStructFactory()
        );

        return $appleJwtFetchingService->getJwtPayload($jwt);
    }
    catch (ValidationFailedException $exception)
    {
        return $exception;
    }
}