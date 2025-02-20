<?php
// Används för att styra huvudmenyn.
const PAGE = 'sida4';
/**
 * Funktionen beräknar omkretsen på en rektangel.
 * Den är beroende av funktionen `calculateArea`, som beräknar rektangelns area.
 * Om `calculateArea` inte returnerar ett giltigt resultat, kan omkretsen inte beräknas.
 *
 * @param int $width Bredden på rektangeln
 * @param int $length Längden på rektangeln
 * @return string Omkretsen på rektangeln
 * @see calculateArea
 */
function calculateCircumference(int $width = 0, int $length = 0): string
{
    $circomference = 2 * ($width + $length);
    $area = calculateArea($width, $length);
    return "Area: $area, Omkrets: $circomference";
}
/**
 * Funktionen beräknar arean av en rektangel.
 *
 * @param int $width Bredden på rektangeln
 * @param int $length Längden på rektangeln
 * @return int Omkretsen på rektangeln
 */
function calculateArea(int $width, int $length): int
{
    return $width * $length;
}
//Ta emot post-data från formuläret, om det inte finns någon sätts det till 0.
$width = intval($_POST['width'] ?? 0);
$length = intval($_POST['length'] ?? 0);

//Beräkna area och omkrets, spara till en variabel som skrivs ut i HTML:en.
$areaAndCircomference = calculateCircumference($width, $length);
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
        <div id="sida4">
            <h1>Sida 4</h1>
            <p>Den här sidan innehåller ett verktyg för att beräkna area och omkrets på en rektangel</p>
            <p>OBS: Endast heltal beräknas</p>
            <form method="post">
                <label for="length">Längd</label>
                <input type="number" name="length" id="length" step="1" value="<?= $length; ?>">
                <label for="width">Bredd</label>
                <input type="number" name="width" id="width" step="1" value="<?= $width; ?>">
                <input type="submit" value="Beräkna">
            </form>
            <p><?= $areaAndCircomference; ?></p>
        </div>
    </main>
    <?php include 'components/footer.php'; ?>
</body>

</html>