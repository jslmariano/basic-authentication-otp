<?php

namespace Jslmariano\AuthenticationOtp\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $table = 'user_otp';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'user_id',
        'email',
        'phone',
        'code',
        'is_verified',
        'updated_at',
    ];

    public function user()
    {
        return $this->hasOne('\App\Models\User', 'id', 'user_id');
    }
}
