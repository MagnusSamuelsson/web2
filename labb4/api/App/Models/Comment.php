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
    public bool $liked_by_current_user;
    public ?User $user;

    /**
     * Validerar the kommentaren
     *
     * @return array Enligt array med fältnamn som nycklar och felmeddelanden som värden.
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
