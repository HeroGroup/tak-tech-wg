<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Privileges;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'can_create' => 'boolean',
        'can_edit' => 'boolean',
        'can_enable' => 'boolean',
        'can_disable' => 'boolean',
        'can_regenerate' => 'boolean',
        'can_remove' => 'boolean',
        'access_violations' => 'boolean'
    ];

    protected function isAdmin(): Attribute
    {
        return Attribute::make(
            get: fn () => in_array($this->user_type, [UserType::SUPERADMIN->value, UserType::ADMIN->value]),
        );
    }

    protected function canCreate(): Attribute
    {
        return Attribute::make(
            get: fn () => DB::table('user_privileges')->where('user_id', $this->id)->where('action', Privileges::CREATE->value)->count() > 0,
        );
    }

    protected function canEdit(): Attribute
    {
        return Attribute::make(
            get: fn () => DB::table('user_privileges')->where('user_id', $this->id)->where('action', Privileges::EDIT->value)->count() > 0,
        );
    }

    protected function canEnable(): Attribute
    {
        return Attribute::make(
            get: fn () => DB::table('user_privileges')->where('user_id', $this->id)->where('action', Privileges::ENABLE->value)->count() > 0,
        );
    }

    protected function canDisable(): Attribute
    {
        return Attribute::make(
            get: fn () => DB::table('user_privileges')->where('user_id', $this->id)->where('action', Privileges::DISABLE->value)->count() > 0,
        );
    }

    protected function canRegenerate(): Attribute
    {
        return Attribute::make(
            get: fn () => DB::table('user_privileges')->where('user_id', $this->id)->where('action', Privileges::REGENERATE->value)->count() > 0,
        );
    }

    protected function canRemove(): Attribute
    {
        return Attribute::make(
            get: fn () => DB::table('user_privileges')->where('user_id', $this->id)->where('action', Privileges::REMOVE->value)->count() > 0,
        );
    }

    protected function accessViolations(): Attribute
    {
        return Attribute::make(
            get: fn () => DB::table('user_privileges')->where('user_id', $this->id)->where('action', Privileges::VIOLATIONS->value)->count() > 0,
        );
    }
}
