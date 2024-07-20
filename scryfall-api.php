<?php
// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "asdqwe123";
$dbname = "mtg";

// Crear la conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

function fetch_cards()
{
    $url = "https://api.scryfall.com/cards/search?q=all";
    $response = file_get_contents($url);
    if ($response !== FALSE) {
        return json_decode($response, true);
    } else {
        throw new Exception("Error fetching data");
    }
}

function prepare_value($field, $value)
{
    if ($value === '' || $value === null) {
        return NULL;
    }
    // Check for boolean fields
    if (in_array($field, ['reserved', 'foil', 'nonfoil', 'oversized', 'promo', 'reprint', 'digital', 'full_art', 'textless', 'booster', 'story_spotlight'])) {
        return (int)$value;
    }
    // Check for integer fields
    if (in_array($field, ['cmc', 'edhrec_rank', 'penny_rank'])) {
        return (int)$value;
    }
    return is_array($value) ? json_encode($value) : $value;
}

function insert_card($conn, $card)
{
    // Campos que potencialmente pueden estar presentes en los datos de la carta
    $possible_fields = [
        'id', 'oracle_id', 'multiverse_ids', 'mtgo_id', 'mtgo_foil_id', 'arena_id', 'tcgplayer_id', 'cardmarket_id', 'name', 'lang',
        'released_at', 'uri', 'scryfall_uri', 'layout', 'highres_image', 'image_status', 'image_uris', 'mana_cost', 'cmc', 'type_line',
        'oracle_text', 'power', 'toughness', 'colors', 'color_identity', 'keywords', 'legalities', 'games', 'reserved', 'foil', 'nonfoil',
        'finishes', 'oversized', 'promo', 'reprint', 'variation', 'set_id', 'sett', 'set_name', 'set_type', 'set_uri', 'set_search_uri',
        'scryfall_set_uri', 'rulings_uri', 'prints_search_uri', 'collector_number', 'digital', 'rarity', 'flavor_text', 'card_back_id',
        'artist', 'artist_ids', 'illustration_id', 'border_color', 'frame', 'full_art', 'textless', 'booster', 'story_spotlight', 'edhrec_rank',
        'penny_rank', 'prices', 'related_uris', 'purchase_uris'
    ];

    // Filtrar los campos que están presentes en los datos de la carta
    $fields = [];
    $values = [];
    $placeholders = [];

    foreach ($possible_fields as $field) {
        if (isset($card[$field])) {
            $fields[] = $field;
            $values[] = prepare_value($field, $card[$field]);
            $placeholders[] = '?';
        }
    }

    if (empty($fields)) {
        throw new Exception("No valid fields to insert for card");
    }

    // Construir la consulta SQL
    $sql = sprintf(
        "INSERT INTO cards (%s) VALUES (%s)",
        implode(", ", $fields),
        implode(", ", $placeholders)
    );

    $stmt = $conn->prepare($sql);

    // Generar los tipos de parámetros para bind_param
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);

    if ($stmt->execute() === TRUE) {
        echo "Registro insertado con éxito<br>";
    } else {
        echo "Error: " . $stmt->error . "<br>";
    }

    $stmt->close();
}

// Ejemplo de uso
try {
    $data = fetch_cards();
    if (isset($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as $card) {
            insert_card($conn, $card);
        }
    } else {
        throw new Exception("La respuesta de la API no contiene datos válidos.");
    }
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage();
}

// Cerrar la conexión a la base de datos
$conn->close();
