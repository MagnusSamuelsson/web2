<?php

namespace App\Models;

class User {

    public ?int $id = null;
    public string $username;
    public string $profile_image;
    public ?string $password;
    public string $description;
    public ?int $number_of_posts;
    public ?int $number_of_followers;
    public ?int $number_of_following;
    public ?bool $is_following_current_user;
    public ?bool $is_followed_by_current_user;
    private string $created_at;
    private string $updated_at;

    public function validateUsername(): bool
    {
        return preg_match('/^[a-zA-Z0-9_åäöÅÄÖ-]{3,20}$/', $this->username);
    }
    public function setPassword(string $password): bool
    {
        if (strlen($password) <= 5) {
            return false;
        }
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        return true;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    public function sanitizeDescription(): self
    {
        $this->description = trim(preg_replace('/\n\n+/', "\n", $this->description));
        return $this;
    }
    public function validateDescription(): true|array
    {
        $maxContentLength = 500;
        $maxNewLines = 7;
        $errors = [];

        if (mb_trim($this->description) === '') {
            $errors[] = 'Content is required';
        }

        if (mb_strlen($this->description) > $maxContentLength) {
            $errors[] = "Content is too long (max $maxContentLength characters)";
        }

        if (preg_match_all('/\R/', $this->description, $matches) > $maxNewLines - 1) {
            $errors[] = "Content cannot contain more than $maxNewLines new lines";
        }
        if (count($errors) === 0) {
            return true;
        }
        return $errors;
    }
}