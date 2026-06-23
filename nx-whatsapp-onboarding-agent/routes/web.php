<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/profile-generation', function () {
    return file_get_contents(resource_path('views/profile_generation.html'));
})->name('profile.generation.form');
