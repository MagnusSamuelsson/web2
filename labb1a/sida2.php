<?php
// Används för att styra huvudmenyn.
const PAGE = 'sida2';

// Ta emot post-data från formuläret, om det inte finns sätts det till en tom array.
$farmAnimals = $_POST['djur'] ?? [];

// Rensa bort onödiga mellanslag i början och slutet av alla strängar i arrayen.
$farmAnimals = array_map('trim', $farmAnimals);

// Ta bort alla djur som är kortare än 2 tecken.
$farmAnimals = array_filter($farmAnimals, fn($x) => strlen($x) > 1);

// Sanera indata.
$farmAnimals = array_map('htmlspecialchars', $farmAnimals);
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
        <div id="sida2">
            <h1>Sida 2</h1>
            <p>Här ska du skriva in tre olika djur, sedan kommer programmet utföra lite operationer på det du skrivit in
            </p>
            <form method="post">
                <label for="djur1">Djur 1:</label>
                <input type="text" id="djur1" name="djur[]" required><br>
                <label for="djur2">Djur 2:</label>
                <input type="text" id="djur2" name="djur[]" required><br>
                <label for="djur3">Djur 3:</label>
                <input type="text" id="djur3" name="djur[]" required><br>
                <input type="submit" value="Skicka">
            </form>
            <?php
            // Om det finns tre djur i arrayen, utför operationerna.
            if (count($farmAnimals) === 3) {

                // Skriv ut arrayen som en sträng.
                echo "<p>" . print_r($farmAnimals, true) . "</p>";

                // Ändra det tredje djuret till "Struts".
                $farmAnimals[2] = "Struts";

                // Lägg till "Alpacka" i arrayen och ta bort det första djuret.
                array_push($farmAnimals, "Alpacka");
                array_shift($farmAnimals);

                // Skriv ut arrayen som en sträng.
                echo "<p>" . print_r($farmAnimals, true) . "</p>";

                // Skriv ut det andra djuret.
                echo "<p>$farmAnimals[1]</p>";
            }
            ?>
        </div>
    </main>
    <?php include 'components/footer.php'; ?>
</body>

</html>