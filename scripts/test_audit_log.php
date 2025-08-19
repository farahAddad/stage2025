<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use App\Entity\AuditLog;
use App\Entity\User;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env.local');

// Configuration Doctrine
$config = Setup::createAnnotationMetadataConfiguration([__DIR__ . '/../src/Entity'], true);
$connection = [
    'driver' => 'pdo_mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? 3306,
    'dbname' => $_ENV['DB_NAME'] ?? 'stage2025',
    'user' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4'
];

try {
    $entityManager = EntityManager::create($connection, $config);
    
    // Créer un audit log de test avec valeurs avant/après
    $auditLog = new AuditLog();
    
    // Simuler un utilisateur (ou récupérer un existant)
    $user = $entityManager->getRepository(User::class)->findOneBy([]);
    if ($user) {
        $auditLog->setUser($user);
    }
    
    $auditLog->setAction('UPDATE');
    $auditLog->setHorodatage(new \DateTime());
    
    // Valeur avant (JSON formaté)
    $valeurAvant = json_encode([
        'nom' => 'Ancien Nom',
        'email' => 'ancien@email.com',
        'role' => 'ROLE_USER',
        'date_creation' => '2024-01-01'
    ], JSON_PRETTY_PRINT);
    
    // Valeur après (JSON formaté)
    $valeurApres = json_encode([
        'nom' => 'Nouveau Nom',
        'email' => 'nouveau@email.com',
        'role' => 'ROLE_ADMIN',
        'date_creation' => '2024-01-01',
        'date_modification' => '2024-12-19'
    ], JSON_PRETTY_PRINT);
    
    $auditLog->setValeurAvant($valeurAvant);
    $auditLog->setValeurApres($valeurApres);
    
    // Persister et sauvegarder
    $entityManager->persist($auditLog);
    $entityManager->flush();
    
    echo "✅ Audit log de test créé avec succès !\n";
    echo "ID: " . $auditLog->getId() . "\n";
    echo "Action: " . $auditLog->getAction() . "\n";
    echo "Valeur avant: " . strlen($auditLog->getValeurAvant()) . " caractères\n";
    echo "Valeur après: " . strlen($auditLog->getValeurApres()) . " caractères\n";
    
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
