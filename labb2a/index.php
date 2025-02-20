<?php
spl_autoload_register(function ($class) {
    include "class/$class.php";
});

$session = new SessionManager();
$userDbh = new UserDatabaseHandler();
$auth = new Auth($userDbh, $session);

/**
 * Kolla om användaren redan är inloggad
 * Om användaren inte inloggad skickas hen till login.php
 */
if (!$auth->check()) {
    header('Location: login.php');
    exit;
}

/**
 * Om användaren försöker logga ut
 * logga ut användaren och skicka hen till login.php
 */
if (GetData::get('logout')) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

// Skapa en ny HTML-sida med titeln 'uppgift 2a'
$page = new HTMLPage('uppgift 2a');
$page->addCss('style.css');

// Lägg en h1-tagg i header taggen
$page->addToHeader(
    element: $page->createElement(
        tag: 'h1',
        text: 'Uppgift 2a'
    )
);

// Lägg en h2-tagg i main taggen
$page->addToMain(
    element: $page->createElement(
        tag: 'h2',
        text: "Hej {$auth->user()->username}"
    )
);

// Skapa en länk för att logga ut.
$logoutButton = $page->createElement(
    tag: 'a',
    text: 'Logga ut',
    class: 'button',
    attributes: [
        'href' => '?logout=1'
    ]
);

$p = $page->createElement(
    tag: 'p',
    children: [
        $logoutButton
    ]
);

// Lägg till länken i main taggen.
$page->addToMain($p);

// Skriv ut sidan.
echo $page->render();
