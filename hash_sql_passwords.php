<?php
// Función para hashear una contraseña
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Función para formatear un valor SQL
function formatSqlValue($value, $isNumeric = false) {
    if ($isNumeric) {
        return $value;
    }
    return "'" . str_replace("'", "''", $value) . "'";
}

// Configuración
$inputFile = 'passwords.sql';
$outputFile = 'passwords_hashed.sql';
$limit = 177; // Número máximo de inserciones a procesar

// Leer el contenido del archivo
$content = file_get_contents($inputFile);

if ($content === false) {
    die("No se pudo leer el archivo de entrada.");
}

// Extraer la parte de INSERT INTO y los valores
if (preg_match('/^(INSERT INTO `users`.*?VALUES\s*)(\(.*\));?$/s', $content, $matches)) {
    $insertStatement = $matches[1];
    $valuesContent = $matches[2];
} else {
    die("No se pudo encontrar la estructura INSERT INTO en el archivo de entrada.");
}

// Separar los conjuntos de valores
$valuesSets = explode('),', $valuesContent);

$newValuesSets = [];
$count = 0;
foreach ($valuesSets as $valueSet) {
    if ($count >= $limit) {
        break; // Detener el procesamiento si se alcanza el límite
    }
    
    // Eliminar paréntesis y espacios extras
    $valueSet = trim($valueSet, "() \n\r\t\v\0");
    $values = str_getcsv($valueSet, ',', "'");
    
    // Índice de la contraseña (ajusta esto según la posición en tu esquema)
    $passwordIndex = 12; // Asumiendo que la contraseña es el 13º campo (índice 12)
    
    // Hashear la contraseña
    $values[$passwordIndex] = hashPassword(trim($values[$passwordIndex], "'"));
    
    // Formatear los valores correctamente
    $formattedValues = array_map(function($index, $value) {
        // Asumimos que id, rol son numéricos, ajusta según sea necesario
        $isNumeric = in_array($index, [0, 13]); 
        return formatSqlValue($value, $isNumeric);
    }, array_keys($values), $values);
    
    $newValuesSets[] = "(" . implode(', ', $formattedValues) . ")";
    $count++;
}

// Reconstruir la consulta SQL completa
$newSql = $insertStatement . implode(",\n", $newValuesSets) . ";";

// Escribir en el archivo de salida
if (file_put_contents($outputFile, $newSql) !== false) {
    echo "Archivo SQL con contraseñas hasheadas generado con éxito: $outputFile\n";
    echo "Se procesaron $count inserciones (límite: $limit).\n";
} else {
    echo "Error al escribir en el archivo de salida.\n";
}