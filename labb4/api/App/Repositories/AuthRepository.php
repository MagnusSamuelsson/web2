<?php

namespace App\Repositories;

use App\Core\Environment;
use App\Services\Dbh;
use App\Services\ErrorHandler;
use App\Models\RefreshToken;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\Key;
use InvalidArgumentException;
use UnexpectedValueException;
use DomainException;
use DateTime;
use stdClass;

class AuthRepository
{

    public function __construct(
        private Dbh $dbh,
        private Environment $env,
        private ErrorHandler $errorHandler
    ) {
    }

    public function getRefreshToken(string $refreshToken): ?RefreshToken
    {
        $db = $this->dbh->getConnection();
        $sql = <<<'SQL'
            SELECT *
            FROM refresh_tokens
            WHERE
                token = :token
                AND expires_at > NOW();
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindParam('token', $refreshToken);
        $stmt->execute();
        return $stmt->fetchObject(RefreshToken::class) ?: null;
    }

    public function createNewRefreshToken(int $userId): RefreshToken
    {
        $refreshToken = new RefreshToken();
        $refreshToken->token = bin2hex(random_bytes(32));
        $refreshToken->user_id = $userId;
        $this->setRefreshTokenExpiration($refreshToken);

        $db = $this->dbh->getConnection();
        $sql = <<<'SQL'
            INSERT INTO refresh_tokens (
                token,
                user_id,
                expires_at
            )
            VALUES (
                :token,
                :user_id,
                :expires_at
            );
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindParam('expires_at', $refreshToken->expires_at);
        $stmt->bindParam('token', $refreshToken->token);
        $stmt->bindParam('user_id', $refreshToken->user_id, $db::PARAM_INT);
        $stmt->execute();
        $refreshToken->id = $db->lastInsertId();
        return $refreshToken;
    }

    public function setRefreshTokenExpiration(RefreshToken $refreshToken): self
    {
        $refreshTokenExpirationDays = Environment::get('REFRESH_TOKEN_EXPIRATION_DAYS');
        $refreshTokenExpiration = new DateTime("+$refreshTokenExpirationDays days");
        $refreshToken->expires_at = $refreshTokenExpiration->format('Y-m-d H:i:s');
        return $this;
    }

    public function generateRefreshToken(RefreshToken $refreshToken): self
    {
        $refreshToken->token = bin2hex(random_bytes(32));
        $this->setRefreshTokenExpiration($refreshToken);
        return $this;
    }


    public function updateRefreshToken(RefreshToken $refreshToken): self
    {
        $db = $this->dbh->getConnection();
        $sql = <<<'SQL'
            UPDATE refresh_tokens
            SET
                token = :token,
                expires_at = :expires_at
            WHERE id = :id;
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindParam('token', $refreshToken->token);
        $stmt->bindParam('expires_at', $refreshToken->expires_at);
        $stmt->bindParam('id', $refreshToken->id, $db::PARAM_INT);
        $stmt->execute();
        return $this;
    }

    public function deleteRefreshToken(string $refreshToken): void
    {
        $db = $this->dbh->getConnection();
        $sql = <<<'SQL'
            DELETE FROM refresh_tokens
            WHERE token = :token;
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindParam('token', $refreshToken);
        $stmt->execute();
    }



    public function generateAccessToken(int $userId): string
    {
        $key = Environment::get('JWT_SECRET');
        $payload = [
            "user_id" => $userId,
            "exp" => time() + Environment::get('ACCESS_TOKEN_EXPIRATION_MINUTES') * 60,
            "iat" => time()

        ];
        $jwt = JWT::encode($payload, $key, Environment::get('JWT_ALGORITHM'));
        return $jwt;
    }

    public function validateAccessToken(string $accessToken): stdClass|bool
    {
        $key = Environment::get('JWT_SECRET');
        $algorithm = Environment::get('JWT_ALGORITHM');
        $key = new Key($key, $algorithm);
        try {
            return JWT::decode($accessToken, $key);
        } catch (ExpiredException $e) {
            $this->errorHandler->handleError($e);
            return false;
        } catch (BeforeValidException $e) {
            $this->errorHandler->handleError($e);
            return false;
        } catch (SignatureInvalidException $e) {
            $this->errorHandler->handleError($e);
            return false;
        } catch (UnexpectedValueException $e) {
            $this->errorHandler->handleError($e);
            return false;
        } catch (DomainException $e) {
            $this->errorHandler->handleError($e);
            return false;
        } catch (InvalidArgumentException $e) {
            $this->errorHandler->handleError($e);
            return false;
        }
    }

}