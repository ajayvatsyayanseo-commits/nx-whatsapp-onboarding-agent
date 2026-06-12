<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Models;

use Illuminate\Database\Eloquent\Model;

final class Register extends Model
{
    protected $table = 'register';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'password',
        'user_type',
        'phone',
        'dob',
        'avatar',
        'gender',
        'date',
        'address',
        'city',
        'district',
        'state',
        'pincode',
        'c_password',
        'otp',
        'class_type',
        'otp_status',
        'status',
        'join_as',
        'for_class',
        'frount_image',
        'back_image',
        'degree',
        'experience',
        'education',
        'budget',
        'other_education',
        'document_type',
        'document_number',
        'profile',
        'profile_desc',
        'pro_desc',
        'force_password_reset',
    ];

    protected $hidden = ['password', 'c_password', 'otp'];

    protected $casts = [
        'dob' => 'date',
        'date' => 'date',
        'force_password_reset' => 'boolean',
    ];
}
