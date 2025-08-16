<?php
require_once __DIR__ . '/../vendor/autoload.php';

try {
    // Connexion à la base de données PostgreSQL
    $pdo = new PDO(
        'pgsql:host=localhost;dbname=app;port=5432',
        'postgres',
        'postgres'
    );
    
    echo "✅ Connexion à la base de données réussie\n\n";
    
    // Vérifier la structure de la table evaluation
    echo "📋 Structure de la table evaluation :\n";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'evaluation' ORDER BY ordinal_position");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['column_name']} ({$row['data_type']})\n";
    }
    echo "\n";
    
    // Vérifier toutes les évaluations
    echo "📊 Toutes les évaluations :\n";
    $stmt = $pdo->query("SELECT * FROM evaluation ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  ID: {$row['id']}, Session: {$row['session_id']}, User: {$row['user_id']}, ";
        echo "Note Globale: {$row['note_globale']}, Clarté: {$row['clarte']}, Pertinence: {$row['pertinence']}\n";
    }
    echo "\n";
    
    // Compter par note globale
    echo "🔢 Répartition par note globale :\n";
    $stmt = $pdo->query("SELECT note_globale, COUNT(*) as nombre FROM evaluation GROUP BY note_globale ORDER BY note_globale");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  Note {$row['note_globale']}: {$row['nombre']} évaluation(s)\n";
    }
    echo "\n";
    
    // Calculer les statistiques de satisfaction selon vos besoins
    echo "📈 Statistiques de satisfaction (selon vos besoins) :\n";
    
    // Très satisfait (4-5)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluation WHERE note_globale >= 4");
    $stmt->execute();
    $tresSatisfait = $stmt->fetchColumn();
    echo "  Très satisfait (4-5): {$tresSatisfait} évaluation(s)\n";
    
    // Satisfait (2-3)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluation WHERE note_globale >= 2 AND note_globale < 4");
    $stmt->execute();
    $satisfait = $stmt->fetchColumn();
    echo "  Satisfait (2-3): {$satisfait} évaluation(s)\n";
    
    // Non satisfait (1)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluation WHERE note_globale = 1");
    $stmt->execute();
    $nonSatisfait = $stmt->fetchColumn();
    echo "  Non satisfait (1): {$nonSatisfait} évaluation(s)\n";
    
    // Total
    $total = $tresSatisfait + $satisfait + $nonSatisfait;
    echo "  Total: {$total} évaluation(s)\n";
    
    // Moyennes
    echo "\n📊 Moyennes :\n";
    $stmt = $pdo->query("SELECT AVG(note_globale) as moyenne_globale, AVG(clarte) as moyenne_clarte, AVG(pertinence) as moyenne_pertinence FROM evaluation");
    $moyennes = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  Note globale moyenne: " . round($moyennes['moyenne_globale'], 2) . "\n";
    echo "  Clarté moyenne: " . round($moyennes['moyenne_clarte'], 2) . "\n";
    echo "  Pertinence moyenne: " . round($moyennes['moyenne_pertinence'], 2) . "\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur de base de données: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
