<?php

namespace App\Models;

class RefreshToken
{
    public ?int $id = null;
    public string $token;
    public int $user_id;
    public string $expires_at;
}
