<?php

namespace App\Models;

class Like
{

    public int $id;
    public int $user_id;
    public int $post_id;
    public string $created_at;
    public ?User $user;
}