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
ini_set('memory_limit', '1024M');
set_time_limit(0);

function delay($milliseconds)
{
    usleep($milliseconds * 1000); // Convertir milisegundos a microsegundos
}

function fetch_cards_page($page)
{
    $url = "https://api.scryfall.com/cards/search?q=*&page=$page";
    $response = file_get_contents($url);
    if ($response !== FALSE) {
        $data = json_decode($response, true);
        if (isset($data['data']) && is_array($data['data'])) {
            return $data;
        } else {
            throw new Exception("La respuesta de la API no contiene datos válidos.");
        }
    } else {
        throw new Exception("Error fetching data");
    }
}

function insert_or_update_card($conn, $card)
{
    $possible_fields = [
        'id', 'oracle_id', 'multiverse_ids', 'mtgo_id', 'mtgo_foil_id', 'arena_id', 'tcgplayer_id', 'cardmarket_id', 'name', 'lang',
        'released_at', 'uri', 'scryfall_uri', 'layout', 'highres_image', 'image_status', 'image_uris', 'mana_cost', 'cmc', 'type_line',
        'oracle_text', 'power', 'toughness', 'colors', 'color_identity', 'keywords', 'legalities', 'games', 'reserved', 'foil', 'nonfoil',
        'finishes', 'oversized', 'promo', 'reprint', 'variation', 'set_id', 'sett', 'set_name', 'set_type', 'set_uri', 'set_search_uri',
        'scryfall_set_uri', 'rulings_uri', 'prints_search_uri', 'collector_number', 'digital', 'rarity', 'flavor_text', 'card_back_id',
        'artist', 'artist_ids', 'illustration_id', 'border_color', 'frame', 'full_art', 'textless', 'booster', 'story_spotlight', 'edhrec_rank',
        'penny_rank', 'prices', 'related_uris', 'purchase_uris'
    ];

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

    $sql = sprintf(
        "INSERT INTO cards (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s",
        implode(", ", $fields),
        implode(", ", $placeholders),
        implode(", ", array_map(function ($field) {
            return "$field = VALUES($field)";
        }, $fields))
    );

    $stmt = $conn->prepare($sql);
    $types = determine_param_types($values);
    $stmt->bind_param($types, ...$values);

    if ($stmt->execute() === TRUE) {
        echo "Registro insertado o actualizado con éxito<br>";
    } else {
        echo "Error: " . $stmt->error . "<br>";
    }

    $stmt->close();
}

function prepare_value($field, $value)
{
    if (is_array($value)) {
        return json_encode($value);
    }

    $integer_fields = ['cmc', 'reserved', 'foil', 'nonfoil', 'oversized', 'promo', 'reprint', 'variation', 'digital', 'full_art', 'textless', 'booster', 'story_spotlight', 'edhrec_rank', 'penny_rank', 'highres_image', 'collector_number'];
    $boolean_fields = ['reserved', 'foil', 'nonfoil', 'oversized', 'promo', 'reprint', 'variation', 'digital', 'full_art', 'textless', 'booster', 'story_spotlight'];

    if (in_array($field, $integer_fields)) {
        return ($value === '' || $value === null) ? 0 : (int)$value;
    }

    if (in_array($field, $boolean_fields)) {
        return ($value === '' || $value === null) ? 0 : (bool)$value;
    }

    return $value;
}

function determine_param_types($values)
{
    $types = '';
    foreach ($values as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_double($value)) {
            $types .= 'd';
        } elseif (is_bool($value)) {
            $types .= 'i'; // MySQLi no tiene un tipo booleano específico, usar 'i' para enteros
        } else {
            $types .= 's';
        }
    }
    return $types;
}

$conn = new mysqli('localhost', 'root', 'asdqwe123', 'mtg');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

try {
    $page = 1;
    $has_more = true;

    while ($has_more) {
        $data = fetch_cards_page($page);
        foreach ($data['data'] as $card) {
            insert_or_update_card($conn, $card);
        }
        $has_more = $data['has_more'];
        $page++;
        delay(100); // Esperar 100 milisegundos entre solicitudes
    }
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage();
}

$conn->close();
