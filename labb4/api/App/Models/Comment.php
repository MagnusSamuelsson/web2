<?php

namespace App\Models;

class Comment
{

    public int $id;
    public int $post_id;
    public int $user_id;
    public string $content;
    public string $created_at;
    public string $updated_at;
    public ?int $reply_comment_id;
    public ?string $deleted_at;
    public ?User $user;

    /**
     * Validates the comment content.
     *
     * @return array<string, string> An associative array with field names as keys and error messages as values.
     */
    public function validate(): true|array
    {
        $maxContentLength = 500;
        $maxNewLines = 7;
        $errors = [];

        if (mb_trim($this->content) === '') {
            $errors['content'][] = 'Content is required';
        }

        if (mb_strlen($this->content) > $maxContentLength) {
            $errors['content'][] = "Content is too long (max $maxContentLength characters)";
        }

        if (preg_match_all('/\R/', $this->content, $matches) > $maxNewLines - 1) {
            $errors['content'][] = "Content cannot contain more than $maxNewLines new lines";
        }
        if (count($errors) === 0) {
            return true;
        }
        return $errors;
    }
}
