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

/**
 * Class AuthRepository
 *
 * Ansvarar för autentisering och hantering av både refresh tokens och access tokens i applikationen.
 * Klassen tillhandahåller metoder för att skapa, hämta, uppdatera samt radera refresh tokens, samt att generera
 * och validera JWT-baserade access tokens.
 */
class AuthRepository
{

    /**
     * Konstruktor för AuthRepository.
     *
     * Initierar AuthRepository med nödvändiga beroenden genom dependency injection. Dessa inkluderar:
     * - En databasanslutning (dbh) för att möjliggöra kommunikation med databasen.
     * - En miljöhanterare (env) för att läsa konfigurationsinställningar via miljövariabler.
     * - En felhanterare (errorHandler) för att fånga och logga undantag som kan uppstå under
     *   token-generering och valideringsprocesser.
     */

    public function __construct(
        private Dbh $dbh,
        private Environment $env,
        private ErrorHandler $errorHandler
    ) {
    }

    /**
     * Hämtar ett aktivt refresh token från databasen.
     *
     * Metoden tar emot ett refresh token som en sträng och söker i databasen efter ett matchande
     * token som inte har överskridit utgångsdatumet. Om ett giltigt token hittas returneras ett
     * instansierat RefreshToken-objekt, annars returneras null. Detta möjliggör kontroll av tokenets
     * giltighet innan vidare autentiseringslogik exekveras.
     *
     * @param string $refreshToken Det refresh token som efterfrågas.
     * @return RefreshToken|null Returnerar ett RefreshToken-objekt vid hittat token, annars null.
     */

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

    /**
     * Skapar ett nytt refresh token för en specifik användare.
     *
     * Denna metod genererar ett slumpmässigt token genom att använda säkerhetsmetoden random_bytes
     * omvandlat till hexadecimal form, sätter dess utgångsdatum via setRefreshTokenExpiration och
     * sparar sedan token i databasen. Efter insättningen hämtas det unikt genererade id:t från databasen,
     * vilket tilldelas refresh token-objektet innan objektet returneras.
     *
     * @param int $userId Användarens unika id för vilket ett nytt token ska skapas.
     * @return RefreshToken Returnerar det nyskapade RefreshToken-objektet med alla nödvändiga attribut.
     *
     */

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

    /**
     * Sätter utgångsdatum för ett refresh token.
     *
     * Metoden beräknar tokenets utgångsdatum baserat på antalet dagar som anges i miljövariabeln
     * 'REFRESH_TOKEN_EXPIRATION_DAYS'. Datumet beräknas som aktuell tid plus angivet antal dagar, och
     * lagras sedan i token-objektet i formatet 'Y-m-d H:i:s'. Metoden returnerar instansen själv för
     * att möjliggöra metodkedjning.
     *
     * @param RefreshToken $refreshToken Det token vars utgångsdatum ska sättas.
     * @return self Returnerar instansen av AuthRepository för möjlig metodkedjning.
     */
    public function setRefreshTokenExpiration(RefreshToken $refreshToken): self
    {
        $refreshTokenExpirationDays = Environment::get('REFRESH_TOKEN_EXPIRATION_DAYS');
        $refreshTokenExpiration = new DateTime("+$refreshTokenExpirationDays days");
        $refreshToken->expires_at = $refreshTokenExpiration->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Genererar ett nytt refresh token.
     *
     * Uppdaterar ett befintligt RefreshToken-objekt genom att tilldela det ett nytt slumpmässigt token
     * samt uppdatera dess utgångsdatum. Detta är användbart vid token-rotation eller när det gamla token har
     * blivit äventyrat. Metoden återvänder instansen för att stödja metodkedjning.
     *
     * @param RefreshToken $refreshToken Token-objektet som ska uppdateras med ett nytt värde och nytt utgångsdatum.
     * @return self Returnerar instansen av AuthRepository för metodkedjning.
     */
    public function generateRefreshToken(RefreshToken $refreshToken): self
    {
        $refreshToken->token = bin2hex(random_bytes(32));
        $this->setRefreshTokenExpiration($refreshToken);
        return $this;
    }

    /**
     * Uppdaterar ett befintligt refresh token i databasen.
     *
     * Metoden tar ett RefreshToken-objekt med aktuella värden för token och utgångsdatum och uppdaterar
     * motsvarande post i databasen baserat på tokenets id. Detta säkerställer att en eventuellt uppdaterad
     * tokeninformation sparas korrekt, vilket är viktigt vid t.ex. tokenförnyelse.
     *
     * @param RefreshToken $refreshToken Token-objektet med de nya värdena som ska uppdateras i databasen.
     * @return self Returnerar instansen av AuthRepository för metodkedjning.
     */
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

    /**
     * Raderar ett refresh token från databasen.
     *
     * Tar ett refresh token som indata och tar bort den tillhörande posten från databasen, vilket effektivt
     * gör token ogiltigt. Denna metod används vid utloggning eller när ett token inte längre ska vara
     * aktivt av säkerhetsskäl.
     *
     * @param string $refreshToken Det refresh token som ska raderas från databasen.
     * @return void Ingen returvärde.
     */
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


    /**
     *
     * Genererar ett nytt access token (JWT) för en angiven användare.
     * Metoden sätter ihop en payload som innehåller användarens id, en utgångstid (exp) samt utfärdandetid (iat).
     * Utgångstiden baseras på en miljövariabel som anger antalet minuter token ska vara giltigt (ACCESS_TOKEN_EXPIRATION_MINUTES).
     * JWT:et kodas med en hemlighet (hämtad från miljövariabeln JWT_SECRET) samt en specificerad algoritm (JWT_ALGORITHM).
     * Den genererade tokensträngen returneras och kan användas för säker kommunikation mellan klient och server.
     *
     * @param int $userId Användarens unika id för vilket access token ska genereras.
     * @return string Den genererade access token (JWT) som kan användas för autentisering.
     */
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

    /**
     * validateAccessToken
     *
     * Validerar ett access token genom att försöka dekoda och verifiera det med hjälp av JWT-biblioteket.
     * Metoden använder miljövariabler för att hämta den nödvändiga hemligheten (JWT_SECRET) och algoritmen (JWT_ALGORITHM),
     * och skapar ett Key-objekt för att verifiera token. Vid dekodning hanteras flera potentiella undantag, såsom
     * utgången token (ExpiredException), signaturproblem (SignatureInvalidException), och andra valideringsfel.
     * Varje undantag fångas upp och vidarebefordras till ErrorHandler för loggning och hantering. Om ett undantag
     * uppstår returneras false, annars returneras ett objekt med tokenens innehåll, vilket möjliggör vidare autentisering
     * i applikationen.
     *
     * @param string $accessToken Den access token (JWT) som ska valideras.
     * @return stdClass|bool Returnerar det dekodade token-innehållet som ett objekt vid lyckad validering, annars false.
     */
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