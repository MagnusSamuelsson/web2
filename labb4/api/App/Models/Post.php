<?php

namespace App\Models;

class Post
{

    public int $id;
    public int $user_id;
    public int $number_of_likes;
    public int $number_of_comments;

    public string $content;
    public array $images;
    public ?bool $liked_by_current_user;
    public string $created_at;
    public string $updated_at;
    public User $user;

    /**
     * Validates the post content.
     *
     * @return array<string, string> An associative array with field names as keys and error messages as values.
     */
    public function validate(): true|array
    {
        $maxContentLength = 500;
        $maxNewLines = 7;
        $errors = [];

        if (mb_trim($this->content) === '') {
            $errors[] = 'Content is required';
        }

        if (mb_strlen($this->content) > $maxContentLength) {
            $errors[] = "Content is too long (max $maxContentLength characters)";
        }

        if (preg_match_all('/\R/', $this->content, $matches) > $maxNewLines - 1) {
            $errors[] = "Content cannot contain more than $maxNewLines new lines";
        }
        if (count($errors) === 0) {
            return true;
        }
        return $errors;
    }
}
