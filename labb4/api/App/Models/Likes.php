<?php

namespace App\Models;

class Likes
{

    public int $user_id;
    public int $post_id;
    public array $likes;
}