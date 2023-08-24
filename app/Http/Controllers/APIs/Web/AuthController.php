<?php

namespace App\Http\Controllers\APIs\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Azimo\Apple\Auth\Exception\ValidationFailedException;
use App\Traits\LoginAttemptsThrottle;
use App\Http\Controllers\APIs\Web\ApiController;
use App\Http\Resources\Web\UserResource;
use App\Models\ErrorLogs;
use App\Models\User;
use App\Models\UserLogins;
use Carbon\Carbon;

/**
 * @group User management
 *
 * APIs for managing users
 */
class AuthController extends ApiController
{
    use LoginAttemptsThrottle;

    /**
     * Log In
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if ($this->hasTooManyLoginAttempts($request))
        {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        $validator = $this->validateLogin();

        // Validate Request
        if ($validator->fails())
        {
            ErrorLogs::addToLog('user login', $validator->messages()->first(), ['email' => $request['email']]);
            return $this->errorResponse(Response::HTTP_CONFLICT, $validator->messages()->first());
        }

        // Validate Email With Specific Conditions
        if (!filter_var($request['email'], FILTER_VALIDATE_EMAIL))
        {
            ErrorLogs::addToLog('user login', __('The email field is only can email or phone numbe'), ['email' => $request['email']]);
            return $this->errorResponse(Response::HTTP_CONFLICT, __('The email field is only can email'));
        }

        $user = User::getByEmail($request['email']);

        // Check If User Exist
        if (!isset($user))
        {
            ErrorLogs::addToLog('user login', __("Account is not exist"), ['email' => $request['email']]);
            return $this->errorResponse(Response::HTTP_CONFLICT, __("Account is not exist"));
        }

        // Check Account Of User If Active Or Not
        if (!$user->is_active)
        {
            ErrorLogs::addToLog('user login', __('Account not active'), ['email' => $request['email']]);
            return $this->errorResponse(Response::HTTP_CONFLICT, __('Account not active'));
        }

        if (!Auth::attempt(['email' => $request['email'], 'password' => $request->password]))
        {
            ErrorLogs::addToLog('user login', __("Password is invalid"), ['email' => $request['email']]);
            return $this->errorResponse(Response::HTTP_CONFLICT, __("Password is invalid"));
        }
        
        $accessToken = $user->createToken('authToken')->accessToken;

        UserLogins::insertLog($user->id, 'Login', 'Website', null, null);

        return $this->respondWithToken($accessToken, new UserResource($user) , null);
    }

    public function validateLogin()
    {
        return Validator::make(request()->all(), [
            'email' => 'required|max:255',
            'password' => 'required|min:8'
        ]);
    }

    public function signup(Request $request)
    {
        $validator = $this->validateSignup();

        if ($validator->fails())
        {
            ErrorLogs::addToLog('user signup', $validator->messages()->first(), $request->except(['password']));
            return $this->errorResponse(Response::HTTP_CONFLICT, $validator->messages()->first());
        }

        $userEmail = User::where('email', '=', $request['email'])->first();
        
        if ($userEmail)
        {
            if (isset($userEmail->id) && !User::checkActiveUserById($userEmail->id)){
                return $this->errorResponse(Response::HTTP_CONFLICT, 'Account not active');
            }
            return $this->errorResponse(Response::HTTP_CONFLICT,  __('Email is exists'));
        }

        DB::beginTransaction();
        $attr = $request->only(User::getFillables());
        $attr['password'] = bcrypt($request['password']);
        $user = new User($attr);
        // set user active
        $user["is_active"] = 1;
        $user->save();

        $user = User::getByEmail($request['email']);

        UserLogins::insertLog($user->id, 'Signup', 'Website', $request->headers->get('purchase-key'));

        DB::commit();

        $accessToken = $user->createToken('authToken')->accessToken;

        return $this->respondSingupWithToken($accessToken, $user);   
    }

    public function validateSignup()
    {
        return Validator::make(request()->all(), [
            'user_name' => 'required|string',
            'email' => 'required|string|regex:/(.+)@(.+)\.(.+)/i',
            'password' => 'required|min:8',
        ]);
    }

    public function loginWithSocial(Request $request)
    {
        $provider = $request->query('provider');
        $is_signup = 0;

        if ($provider != "twitter" && $provider != "linkedin" && $provider != "apple")
        {
            $validator = $this->validateLoginWithSocial();

            if ($validator->fails())
            {
                ErrorLogs::addToLog('user social login', $validator->errors()->first(), ['name' => $request["name"], 'email' => $request["email"], 'provider' => $provider]);
                return $this->errorResponse(Response::HTTP_CONFLICT, $validator->errors()->first());
            }
        }
        elseif ($provider == 'apple')
        {
            $validator = Validator::make(request()->all(), [
                'name' => 'nullable',
                'token' => 'required',
                'source' => 'required'
            ]);

            if ($validator->fails())
            {
                ErrorLogs::addToLog('user social login', $validator->errors()->first(), ['name' => $request["name"], 'provider' => $provider]);
                return $this->errorResponse(Response::HTTP_CONFLICT, $validator->errors()->first());
            }
        }
        else
        {
            $validator = $this->validateLoginWithTwitter();

            if ($validator->fails())
            {
                ErrorLogs::addToLog('user social login', $validator->errors()->first(), ['name' => $request["name"], 'email' => $request["email"], 'provider' => $provider]);
                return $this->errorResponse(Response::HTTP_CONFLICT, $validator->errors()->first());
            }
        }

        if ($provider == "apple")
        {
            $payload = retrieveJwtPayload($request->token, $validator->validated()['source']);

            if (!$payload instanceof ValidationFailedException) {

                $user = User::getUserByUserAppleId($payload->getSub());

                if (!isset($user))
                {
                    // check if user have already account with this email and link it with social login
                    $user = User::getUserByEmail($payload->getEmail());
                    if ($user)
                    {
                        $user['apple_provider_id'] = $payload->getSub();
                        if ($request["user_image"]) $user['user_image'] = $request["user_image"];
                        $user->save();
                    }
                }
                else if ($request["user_image"])
                {
                    $user['user_image'] = $request["user_image"];
                    $user->save();
                }

                if (!isset($user))
                {
                    // create new user
                    $is_signup = 1;
                    $user = User::createUserBySocialMedia($validator->validated()['name'] ?? '', $payload->getEmail(), null, null, null, null, $payload->getSub(), null);
                }

                // create token for user
                $accessToken = $user->createToken('authToken')->accessToken;
                UserLogins::insertLog($user->id, 'Login', 'Social-Mobile', null, $request->headers->get('clientId'), $provider);
                if ($request->device_token != "" && $request->device_type != "") Devicetoken::StoreDeviceToken($request->device_token, $request->device_type, $user->id);
                
                return $this->respondWithToken($accessToken, $user, $is_signup, $message);
            }
            else return $this->errorResponse(Response::HTTP_CONFLICT, $payload->getMessage());
        }
        elseif ($provider == "google")
        {
            // call api to check validity of token
            $res = Http::get("https://www.googleapis.com/oauth2/v3/tokeninfo", ['access_token' => $request['token']]);

            if (!isset($res['error_description']))
            {
                // get google user id from calling google api
                $google_user_id = $res["sub"];

                if ($request["id"] == $google_user_id)
                {
                    // check if user have account with this social user id
                    $user = User::getUserByUserGoogleId($google_user_id);

                    if (!isset($user))
                    {
                        // check if user have already account with this email and link it with social login
                        $user = User::getUserByEmail($res["email"]);
                        
                        if ($user)
                        {
                            $user['google_provider_id'] = $google_user_id;
                            //update user image to Google profile image
                            if ($request["user_image"]) $user['user_image'] = $request["user_image"];
                            $user->save();
                        }
                    }
                    else if ($request["user_image"])
                    {
                        $user['user_image'] = $request["user_image"];
                        $user->save();
                    }

                    if (!isset($user))
                    {
                        // create new user
                        $is_signup = 1;
                        $user = User::createUserBySocialMedia($request["name"], $request["email"], $google_user_id, null, null, null, $request["user_picture"]);
                    }

                    // create token for user
                    $accessToken = $user->createToken('authToken')->accessToken;
                    UserLogins::insertLog($user->id, 'Login', 'Social-Mobile', null, $request->headers->get('clientId'), $provider);

                    return $this->respondWithToken($accessToken, $user, $is_signup, $message);
                }
            }
            else
            {
                ErrorLogs::addToLog('user social login', $res['error_description'] ?? '', ['name' => $request["name"], 'email' => $request["email"], 'provider' => $provider]);
                return $this->errorResponse(Response::HTTP_CONFLICT, $res['error_description'] ?? '');
            }
        }
        elseif ($provider == "facebook")
        {
            // call api to check validity of token
            $email = '';
            $res = Http::get("https://graph.facebook.com/" . $request["id"], ['fields' => "id,name,email", 'access_token' => $request['token']]);

            if (!isset($res['error']))
            {
                // get google user id from calling google api
                $facebook_user_id = $res["id"];

                if ($request["id"] == $facebook_user_id)
                {
                    // check if user have account with this social user id
                    $user = User::getUserByUserFacebookId($facebook_user_id);
                    
                    if (!isset($user))
                    {
                        if (isset($res["email"]))
                        {
                            // check if user have already account with this email and link it with social login
                            $user = User::getUserByEmail($res["email"]);
                            
                            if ($user)
                            {
                                $user['facebook_provider_id'] = $facebook_user_id;
                                //update user image to facebook profile image
                                if ($request["user_image"]) $user['user_image'] = $request["user_image"];
                                $user->save();
                            }
                            $email = $res["email"];
                        }
                        else $email = $request["email"];
                    }
                    else if ($request["user_image"])
                    {
                        $user['user_image'] = $request["user_image"];
                        $user->save();
                    }
                    
                    if (!isset($user))
                    {
                        // create new user
                        $is_signup = 1;
                        $user = User::createUserBySocialMedia($res["name"], $email, null, $facebook_user_id, null, null, $request["user_picture"]);
                    }

                    // create token for user
                    $accessToken = $user->createToken('authToken')->accessToken;
                    UserLogins::insertLog($user->id, 'Login', 'Social-Mobile', null, $request->headers->get('clientId'), $provider);
                    if ($request->device_token != "" && $request->device_type != "") Devicetoken::StoreDeviceToken($request->device_token, $request->device_type, $user->id);

                    return $this->respondWithToken($accessToken, $user, $is_signup, $message);
                }
            }
            else
            {
                ErrorLogs::addToLog('user social login', $res['error']["message"], ['name' => $request["name"], 'email' => $request["email"], 'provider' => $provider]);
                return $this->errorResponse(Response::HTTP_CONFLICT, $res['error']["message"]);
            }
        }
        elseif ($provider == "twitter")
        {
            $twitter_user_id = $request["id"];
            // check if user have account with this social user id
            $user = User::getUserByUserTwitterId($twitter_user_id);

            if (!isset($user))
            {
                if ($request["email"])
                {
                    // check if user have already account with this email and link it with social login
                    $user = User::getUserByEmail($request["email"]);
                    
                    if ($user)
                    {
                        $user['twitter_provider_id'] = $twitter_user_id;
                        //update user image to twitter profile image
                        if ($request["user_image"]) $user['user_image'] = $request["user_image"];
                        $user->save();
                    }
                }
                else return $this->errorResponse(Response::HTTP_CONFLICT, __('The email field is only can email or phone number'));
            }
            else if ($request["user_image"])
            {
                $user['user_image'] = $request["user_image"];
                $user->save();
            }

            if (!isset($user))
            {
                // create new user
                $is_signup = 1;
                $user = User::createUserBySocialMedia($request->input('name'), $request->input('email'), null, null, $twitter_user_id, null, $request["user_picture"]);
            }

            // create token for user
            $accessToken = $user->createToken('authToken')->accessToken;
            UserLogins::insertLog($user->id, 'Login', 'Social', null, $request->headers->get('clientId'), $provider);
            if ($request->device_token != "" && $request->device_type != "") Devicetoken::StoreDeviceToken($request->device_token, $request->device_type, $user->id);

            return $this->respondWithToken($accessToken, $user, $is_signup, $message);
        }
        elseif ($provider == "linkedin")
        {
            $linkedin_user_id = $request->input('id');

            // check if user have account with this social user id
            $user = User::getUserByUserLinkedinId($linkedin_user_id);

            if (!isset($user))
            {
                if ($request["email"])
                {
                    // check if user have already account with this email and link it with social login
                    $user = User::getUserByEmail($request["email"]);
                    
                    if ($user)
                    {
                        $user['linkedin_provider_id'] = $linkedin_user_id;
                        //update user image to linkedin profile image
                        if ($request["user_image"]) $user['user_image'] = $request["user_image"];
                        $user->save();
                    }
                }
                else return $this->errorResponse(Response::HTTP_CONFLICT, __('The email field is only can email or phone number'));
            }
            else if ($request["user_image"])
            {
                $user['user_image'] = $request["user_image"];
                $user->save();
            }

            if (!isset($user))
            {
                // create new user
                $is_signup = 1;
                $user = User::createUserBySocialMedia($request["name"], $request["email"], null, null, null, $linkedin_user_id, $request["user_picture"]);
            }

            // create token for user
            $accessToken = $user->createToken('authToken')->accessToken;
            UserLogins::insertLog($user->id, 'Login', 'Social', null, $request->headers->get('clientId'), $provider);
            if ($request->device_token != "" && $request->device_type != "") Devicetoken::StoreDeviceToken($request->device_token, $request->device_type, $user->id);

            return $this->respondWithToken($accessToken, $user, $is_signup, $message);
        }

        ErrorLogs::addToLog('user social login', "This provider not supported yet", ['name' => $request["name"], 'email' => $request["email"], 'provider' => $provider]);
        return $this->errorResponse(Response::HTTP_CONFLICT, "This provider not supported yet");
    }

    public function validateLoginWithSocial()
    {
        return Validator::make(request()->all(), [
            'id' => 'required',
            'name' => 'required',
            'token' => 'required'
        ]);
    }

    public function validateLoginWithTwitter()
    {
        return Validator::make(request()->all(), [
            'id' => 'required',
            'name' => 'required'
        ]);
    }

    protected function respondWithToken($token, $data = null, $is_signup = null, $message = null)
    {
        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => $data,
            'is_signup' => $is_signup
        ], $message ?? __("Login Successfully"));
    }

    protected function respondSingupWithToken($token, $user = null)
    {
        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], __("Signup Successfully"));
    }    

    /**
     * Log Out
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        $user = auth()->guard('api')->user();

        if (!isset($user)) return $this->errorResponse(Response::HTTP_CONFLICT, __("No user logged in"));

        auth()->guard('api')->user()->tokens->each(function ($token, $key) {$token->delete();});
        
        $latestUserLogin = UserLogins::where("user_id", $user->id)->where("action", "Login")->orwhere("action", "Signup")->orderByDesc('created_at')->first();

        UserLogins::insertLog(auth()->guard('api')->user()->id, 'Logout', $latestUserLogin->type, null, $request->headers->get('purchase-key'));

        return $this->successResponse([], __("Successfully logged out"));
    }

    public function me()
    {
        return $this->successResponse(new UserResource(auth()->guard('api')->user()));
    }
}