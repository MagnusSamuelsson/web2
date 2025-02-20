<?php
// Används för att styra huvudmenyn.
const PAGE = 'sida1';

/**
 * Funktionen skriver ut en multibytesträng baklänges.
 * Den är nödvändig då många svenska namn innehåller åäö och strrev kan inte hantera det.
 *
 * Se kommentarer i manualen
 *
 * @param string $string Strängen som ska skrivas ut baklänges
 * @return string Strängen baklänges
 * @see https://www.php.net/manual/en/function.strrev.php
 */
function mb_strrev(string $string): string
{
    $charArr = mb_str_split($string, 1);
    return implode('', array_reverse($charArr));
}

// Ta emot post-data från formuläret, om det inte finns sätts det till "Magnus Samuelsson".
$strName = $_POST['name'] ?? "Magnus Samuelsson";

// Rensa bort onödiga mellanslag i början och slutet av strängen.
$strName = mb_trim($strName);

// Räkna antalet tecken i strängen.
$strNameLength = mb_strlen($strName);

// Skriv ut strängen baklänges, och sanera indata.
$strNameReversed = htmlspecialchars(mb_strrev($strName));

// Sanera indata.
$strName = htmlspecialchars($strName);

// Gör om strängen till versaler och gemener.
$strNameCapitalized = mb_strtoupper($strName);
$strNameLower = mb_strtolower($strName);
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
        <div id="sida1">
            <h1>Sida 1</h1>
            <p>Den här sidan har lite olika funktioner som förändrar namnet som du knappar in i formuläret nedan, testa!
            </p>

            <p><?= "Denna text är genererad med utskriftskommandot i PHP"; ?></p>
            <form method="post">
                <label for="name">Namn:</label>
                <input type="text" name="name" id="name">
                <input type="submit" value="Skicka">
            </form>
            <p>Hej <?= $strName; ?></p>
            <p>Baklänges: <?= $strNameReversed; ?></p>
            <p>Gemener: <?= $strNameLower; ?></p>
            <p>Versaler: <?= $strNameCapitalized; ?></p>
            <p>Antal tecken: <?= $strNameLength; ?></p>
        </div>
    </main>
    <?php include 'components/footer.php'; ?>
</body>

</html>