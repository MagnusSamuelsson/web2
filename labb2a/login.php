<?php
spl_autoload_register(function ($class) {
    include "class/$class.php";
});

/**
 * Skapa eller återuppta en session
 * Skapa en instans av UserDatabaseHandler och Auth
 */
$session = new SessionManager();
$userDbh = new UserDatabaseHandler();
$auth = new Auth($userDbh, $session);

/**
 * Kolla om användaren redan är inloggad
 * Om användaren redan är inloggad skickas hen till index.php
 */
if ($auth->check()) {
    header('Location: index.php');
    exit;
}

/**
 * Om användaren försöker logga in eller registrera sig
 * kontrollera att CSRF-token är giltig
 * Om CSRF-token är ogiltig, sätt ett felmeddelande och rensa POST-data
 * Det här är viktigt för att säkerställa att användaren har använt formuläret
 * på sidan och inte skickat en förfrågan från en annan sida
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfToken::validate(PostData::get('csrf'))) {
        $auth->errorMsg = "Ogiltig förfrågan.";
        PostData::clear();
    }
}

/**
 * Om användaren försöker logga in
 * hämta användarnamn och lösenord från POST-data
 * försök logga in användaren
 * om inloggningen lyckas, skicka användaren till index.php
 */
if (PostData::get('login')) {
    $username = PostData::get('username');
    $password = PostData::get('password');
    if ($auth->login($username, $password)) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Om användaren försöker registrera sig
 * hämta användarnamn och lösenord från POST-data
 * försök registrera användaren
 * om registreringen lyckas, logga in användaren och skicka hen till index.php
 */
if (PostData::get('register')) {
    $username = PostData::get('username');
    $password = PostData::get('password');
    if ($auth->register($username, $password)) {
        $auth->login($username, $password);
        header('Location: index.php');
        exit;
    }
}

// Skapa en ny HTML-sida med titeln 'uppgift 2a'.
$page = new HTMLPage('uppgift 2a');
$page->addCss('style.css');

// Lägg en h1-tagg i header taggen.
$page->addToHeader(
    element: $page->createElement(
        tag: 'h1',
        text: 'Uppgift 2a'
    )
);

// Lägg en h2-tagg i main taggen.
$page->addToMain(
    element: $page->createElement(
        tag: 'h2',
        text: 'Logga in eller registrera dig'
    )
);

// Lägg till eventuellt felmeddelande i main taggen.
if (isset($auth->errorMsg)) {
    $page->addToMain(
        element: $page->createElement(
            tag: 'p',
            text: $auth->errorMsg,
            id: null,
            class: 'errormsg'
        )
    );
}

// Skapa ett formulär för att logga in eller registrera sig.
$form = new FormBuilder($page->getDoc());

// Lägg till alla fält i formuläret.
$form->addInput(
    type: 'hidden',
    name: 'csrf',
    value: CsrfToken::generate()
)
    ->addInput(
        type: 'text',
        name: 'username',
        id: 'username',
        value: PostData::getClean('username'),
        required: true,
        label: 'Användarnamn:'
    )
    ->addInput(
        type: 'password',
        name: 'password',
        id: 'password',
        required: true,
        label: 'Lösenord:'
    )
    ->addInput(
        type: 'submit',
        name: 'login',
        value: 'Logga in',
        class: 'button'
    )
    ->addInput(
        type: 'submit',
        name: 'register',
        value: 'Registrera',
        class: 'button'
    );

// Lägg till formuläret i main taggen.
$page->addToMain($form->getForm());

// Skriv ut sidan.
echo $page->render();
