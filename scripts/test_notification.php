<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

echo "🧪 Test des Notifications de Session\n";
echo "====================================\n\n";

// Test 1: Vérifier que les commandes existent
echo "1️⃣ Test de l'existence des commandes...\n";
$output = shell_exec('cd ' . __DIR__ . '/.. && php bin/console list | findstr session');
if ($output) {
    echo "✅ Commandes trouvées:\n";
    echo $output;
} else {
    echo "❌ Commandes session non trouvées\n";
}

echo "\n";

// Test 2: Lister les sessions actuelles
echo "2️⃣ Liste des sessions actuelles...\n";
$output = shell_exec('cd ' . __DIR__ . '/.. && php bin/console app:session-test --list');
if ($output) {
    echo "✅ Sessions listées:\n";
    echo $output;
} else {
    echo "❌ Erreur lors de la liste des sessions\n";
}

echo "\n";

// Test 3: Tester le changement de statut d'une session
echo "3️⃣ Test du changement de statut de la session 2...\n";
$output = shell_exec('cd ' . __DIR__ . '/.. && php bin/console app:session-test --test 2');
if ($output) {
    echo "✅ Test de changement de statut:\n";
    echo $output;
} else {
    echo "❌ Erreur lors du test de changement de statut\n";
}

echo "\n";

// Test 4: Vérifier l'état final
echo "4️⃣ État final des sessions...\n";
$output = shell_exec('cd ' . __DIR__ . '/.. && php bin/console app:session-test --list');
if ($output) {
    echo "✅ État final:\n";
    echo $output;
} else {
    echo "❌ Erreur lors de la vérification de l'état final\n";
}

echo "\n";

// Test 5: Exécuter le scheduler pour voir s'il trouve des sessions à traiter
echo "5️⃣ Test du scheduler principal...\n";
$output = shell_exec('cd ' . __DIR__ . '/.. && php bin/console app:session-scheduler');
if ($output) {
    echo "✅ Scheduler exécuté:\n";
    echo $output;
} else {
    echo "❌ Erreur lors de l'exécution du scheduler\n";
}

echo "\n";
echo "�� Test terminé!\n";
