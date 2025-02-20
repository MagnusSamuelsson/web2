<?php
// Används för att styra huvudmenyn.
const PAGE = 'sida5';

/**
 * Här skriver jag ut informationen direkt från superglobalen $_SERVER.
 *
 * SERVER_NAME: Namnet på servern som kör det aktuella skriptet.
 * REMOTE_ADDR: IP-adressen från vilken användaren tittar på den aktuella sidan.
 * PHP_SELF: Filnamnet på det aktuella skriptet, relativt till dokumentroten.
 * REMOTE_PORT: Porten som används på användarens dator för att kommunicera med webbservern.
 * REQUEST_METHOD: Vilken begäran metod som användes för att komma åt sidan; t.ex. 'GET', 'POST', 'PUT'.
 */
?>
<!DOCTYPE html>
<html lang="sv">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Uppgift 1</title>
</head>

<body>
    <header>
        <h1>Uppgift 1</h1>
        <?php include 'components/menu.php'; ?>
    </header>
    <main>
        <div id="sida5">
            <h1>Sida 5</h1>
            <p>Den här sidan skriver ut lite information från servern</p>
            <p>Serverns namn: <?= $_SERVER['SERVER_NAME']; ?></p>
            <p>Användarens IP: <?= $_SERVER['REMOTE_ADDR']; ?></p>
            <p>Filnamn: <?= $_SERVER['PHP_SELF']; ?></p>
            <p>Port: <?= $_SERVER['REMOTE_PORT']; ?></p>
            <p>Metod: <?= $_SERVER['REQUEST_METHOD']; ?></p>
        </div>
    </main>
    <?php include 'components/footer.php'; ?>
</body>

</html>