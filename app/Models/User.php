<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    public static $SocialSelectColunms = ['id', 'user_name', 'email', 'first_name', 'last_name'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_name', 'first_name', 'last_name', 'email', 'password', 'user_image', 'google_provider_id', 'facebook_provider_id', 'twitter_provider_id',
        'apple_provider_id', 'linkedin_provider_id', 'is_active', 'created_at', 'updated_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [ 'password', 'remember_token'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public static function getFillables()
    {
        return (new User())->fillable;
    }

    public function UserFavorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class);
    }

    public static function getByEmail($email = "")
    {
        return User::where('email', '=', $email)->first();
    }

    public static function checkActiveUserById($user_id)
    {
        $user = User::where('id', '=', $user_id)->first();
        if (!$user) return false;

        return $user->is_active ? true : false;
    }

    public static function getUserByUserGoogleId($provider_id)
    {
        return User::where('google_provider_id', '=', $provider_id)->select(User::$SocialSelectColunms)->first();
    }

    public static function getUserByUserFacebookId($provider_id)
    {
        return User::where('facebook_provider_id', '=', $provider_id)->select(User::$SocialSelectColunms)->first();
    }

    public static function getUserByUserTwitterId($provider_id)
    {
        return User::where('twitter_provider_id', '=', $provider_id)->select(User::$SocialSelectColunms)->first();
    }

    public static function getUserByUserLinkedinId($provider_id)
    {
        return User::where('linkedin_provider_id', '=', $provider_id)->select(User::$SocialSelectColunms)->first();
    }

    public static function getUserByUserAppleId($provider_id)
    {
        return User::where('apple_provider_id', '=', $provider_id)->select(User::$SocialSelectColunms)->first();
    }

    public static function createUserBySocialMedia($username, $email, $google_user_id = null, $facebook_user_id = null, $twitter_user_id = null, $linkedin_user_id = null, $apple_user_id = null, $user_image = null)
    {
        return User::create([
            'user_name' => $username,
            'email' => $email,
            'google_provider_id' => isset($google_user_id) ? $google_user_id : null,
            'facebook_provider_id' => isset($facebook_provider_id) ? $facebook_provider_id : null,
            'twitter_provider_id' => isset($twitter_provider_id) ? $twitter_provider_id : null,
            'linkedin_provider_id' => isset($linkedin_provider_id) ? $linkedin_provider_id : null,
            'apple_provider_id' => isset($apple_provider_id) ? $apple_provider_id : null,
            'user_image' => isset($user_image) ? $user_image : null,
        ]);
    }
}
