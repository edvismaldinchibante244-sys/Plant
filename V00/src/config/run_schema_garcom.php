<?php
require_once 'database.php';
require_once '../Model/Usuario.php'; // for safety

$database = new Database();
$db = $database->getConnection();

$sql_file = 'schema_garcom.sql';

if (!file_exists($sql_file)) {
    die("File $sql_file not found\n");
}

$sql = file_get_contents($sql_file);
$queries = explode(';', $sql);
$success_count = 0;

try {
    $db->beginTransaction();
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        $stmt = $db->prepare($query);
        $stmt->execute();
        $success_count++;
    }
    $db->commit();
    echo "✅ SUCCESS: Executed $success_count queries from $sql_file\n";
    echo "Garçom fields added to pedidos and mesas.\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>

