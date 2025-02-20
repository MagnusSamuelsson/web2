<?php
/**
 * Klass för att hantera autentisering
 *
 * Denna klass innehåller metoder för att hantera autentisering av användare.
 */
class Auth
{
    /**
     * Felmeddelande
     * @var string
     */
    public string $errorMsg;
    public function __construct(
        private UserDatabaseHandler $userDbh,
        private SessionManager $session
    ) {
    }
    /**
     * Loggar in en användare
     *
     * @param string $username Användarnamn
     * @param string $password Lösenord
     * @return bool True om inloggningen lyckades, annars false
     */
    public function login($username, $password): bool
    {
        $user = $this->userDbh->getUserByName($username);
        if ($user !== null && password_verify($password, $user->password)) {
            $this->session->user = $user;
            $this->session->regenerate();
            return true;
        }
        $this->errorMsg = "Fel användarnamn eller lösenord.";
        return false;
    }
    /**
     * Registrerar en ny användare
     * Validering av användarnamn och lösenord görs automatiskt innan registreringen.
     * Användarnamnet får bara innehålla bokstäver och siffror och måste vara minst 3 tecken långt.
     * Lösenordet måste vara minst 8 tecken långt.
     *
     * @param string $username Användarnamn
     * @param string $password Lösenord
     * @return bool True om registreringen lyckades, annars false
     */
    public function register($username, $password): bool
    {
        $existingUser = $this->userDbh->getUserByName($username);
        if ($existingUser === null) {
            if (!$this->validateUsername($username)) {
                $this->errorMsg = "Användarnamnet måste vara minst 3 tecken långt och får endast innehålla bokstäver och siffror.";
                return false;
            }
            if (!$this->validateNewPassword($password)) {
                $this->errorMsg = "Lösenordet måste vara minst 8 tecken långt.";
                return false;
            }
            $user = new User($username, password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]));
            $this->userDbh->addUser($user);
            return true;
        }
        $this->errorMsg = "Användarnamnet är upptaget.";
        return false;
    }
    /**
     * Validerar användarnamn
     * Användarnamnet får bara innehålla bokstäver och siffror och måste vara minst 3 tecken långt.
     *
     * @param string $username Användarnamn
     * @return bool True om användarnamnet är giltigt, annars false
     */
    private function validateUsername($username): bool
    {
        return preg_match('/^[a-zA-Z0-9åäöÅÄÖ]{3,}$/', $username);
    }
    /**
     * Validerar lösenord
     * Lösenordet måste vara minst 8 tecken långt.
     *
     * @param string $password Lösenord
     * @return bool True om lösenordet är giltigt, annars false
     */
    public function validateNewPassword($password): bool
    {
        return strlen($password) >= 8;
    }
    /**
     * Loggar ut en användare
     */
    public function logout(): void
    {
        $this->session
            ->clear()
            ->regenerate();
    }
    /**
     * Kontrollerar om en användare är inloggad
     *
     * @return bool True om en användare är inloggad, annars false
     */
    public function check(): bool
    {
        return isset($this->session->user);
    }
    /**
     * Hämtar inloggad användare
     *
     * @return User Inloggad användare
     */
    public function user(): User
    {
        return $this->session->user;
    }
}
