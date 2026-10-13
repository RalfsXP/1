<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'locale', 'goal_styles'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $appends = ['is_admin'];

    /**
     * Admins (ADMIN_EMAILS) can open the Site health page.
     */
    protected function isAdmin(): Attribute
    {
        return Attribute::get(fn () => in_array(strtolower((string) $this->email), config('health.admin_emails'), true));
    }

    /**
     * Language for mail and notifications sent to this user.
     */
    public function preferredLocale(): string
    {
        return $this->locale ?: config('app.supported_locales')[0];
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function whiteboards(): HasMany
    {
        return $this->hasMany(Whiteboard::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'goal_styles' => 'array',
        ];
    }
}
