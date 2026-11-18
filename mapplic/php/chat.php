<?php
header("Content-Type: application/json; charset=utf-8");

$departamentos = [
    "Amazonas", "Antioquia", "Arauca", "Atlántico", "Bolívar", "Boyacá",
    "Caldas", "Caquetá", "Casanare", "Valle del Cauca", "Cauca", "Cesar", "Chocó", "Córdoba",
    "Cundinamarca", "Guainía", "Guaviare", "Huila", "La Guajira", "Magdalena",
    "Meta", "Nariño", "Norte de Santander", "Putumayo", "Quindío", "Risaralda",
    "San Andrés, Providencia y Santa Catalina", "Santander", "Sucre", "Tolima", "Vaupés", "Vichada", "Bogotá"
];

$categoriasPermitidas = [
    "capital",
    "clima",
    "cultura",
    "demografia",
    "descripcion",
    "economia",
    "etnografia",
    "fauna",
    "flora",
    "festividad",
    "gastronomia",
    "geografia",
    "hidrografia",
    "historia",
    "personajes",
    "sitios"
];

// ===============================
// 1) Entrada
// ===============================
$consulta = $_GET["cadena"] ?? "";

if(trim($consulta) === ""){
    echo json_encode([
        "success" => false,
        "mensaje" => "Debe enviar el parámetro cadena."
    ]);
    exit;
}

// ===============================
// 2) IA: extraer intención + depto
// ===============================
$resultadoIA = procesarConsultaConIA($consulta, $departamentos, $categoriasPermitidas);
if($resultadoIA["success"] === false){
    echo json_encode($resultadoIA);
    exit;
}

$departamentoNombre = $resultadoIA["departamento"];
$categoria = $resultadoIA["categoria"];

// ===============================
// 3) Consultas BD
// ===============================
$conexion_mysql = new mysqli("localhost", "root", "root", "explora");

if ($conexion_mysql->connect_error) {
    echo json_encode([
        "success" => false,
        "mensaje" => "Error conexión MySQL: " . $conexion_mysql->connect_error
    ]);
    exit;
}

$departamentoNombreEsc = $conexion_mysql->real_escape_string($departamentoNombre);

$departamento = $conexion_mysql->query("SELECT * FROM list_departamentos WHERE nombre = '$departamentoNombreEsc'");
$departamento = $departamento->fetch_assoc();

if(!$departamento){
    echo json_encode([
        "success" => false,
        "mensaje" => "No encontré el departamento en BD."
    ]);
    exit;
}

$descripcion = $conexion_mysql->query("SELECT * FROM departamento_descripcion WHERE departamento = {$departamento['id']}");
$descripcion = $descripcion->fetch_assoc();

if(!$descripcion){
    echo json_encode([
        "success" => false,
        "mensaje" => "No encontré descripción para el departamento."
    ]);
    exit;
}

// ===============================
// 4) Mapear categoría => tablas
// ===============================
$mapa = [
    "capital" => ["tabla" => "capital", "campo" => "capital", "list" => 0],
    "clima" => ["tabla" => "clima", "campo" => "clima", "list" => 0],
    "cultura" => ["tabla" => "cultura", "campo" => "descripcion", "list" => 0],
    "demografia" => ["tabla" => "demografia", "campo" => "poblacion", "list" => 0],
    "descripcion" => ["tabla" => "descripcion", "campo" => "contenido", "list" => 0],
    "economia" => ["tabla" => "economia", "campo" => "economia", "list" => 0],
    "etnografia" => ["tabla" => "etnografia", "campo" => "contenido", "list" => "etnografia_list"],
    "fauna" => ["tabla" => "fauna_flora", "campo" => "fauna", "list" => "fauna_list"],
    "flora" => ["tabla" => "fauna_flora", "campo" => "flora", "list" => "flora_list"],
    "festividad" => ["tabla" => 0, "campo" => "contenido", "list" => "festividad_list"],
    "gastronomia" => ["tabla" => "gastronomia", "campo" => "gastronomia", "list" => 0],
    "geografia" => ["tabla" => "geografia", "campo" => "ubicacion", "list" => 0],
    "hidrografia" => ["tabla" => "hidrografia", "campo" => "hidrografia", "list" => 0],
    "historia" => ["tabla" => "historia", "campo" => "historia", "list" => 0],
    "personajes" => ["tabla" => 0, "campo" => "contenido", "list" => "personaje_list"],
    "sitios" => ["tabla" => 0, "campo" => "contenido", "list" => "sitios_list"],
];

$obj = $mapa[$categoria];

// ===============================
// 5) Respuesta
// ===============================
$respuesta = [
    "success" => true,
    "departamento" => $departamentoNombre,
    "categoria" => $categoria,
    "mensaje" => null
];

// descripcion va directo
if($categoria === "descripcion"){
    $q = $conexion_mysql->query("SELECT contenido as descripcion FROM departamento_descripcion WHERE departamento = {$departamento['id']}");
    $respuesta["mensaje"] = $q->fetch_assoc();
}
else{
    if($obj["tabla"] !== 0){
        $tabla = $obj["tabla"];
        $campo = $obj["campo"];

        $q = $conexion_mysql->query("SELECT $campo as descripcion FROM departamento_$tabla WHERE departamento = {$descripcion['id']}");
        $respuesta["mensaje"] = $q->fetch_assoc();
    }
}

// listas
if($obj["list"] !== 0){
    $tablaList = $obj["list"];
    $q = $conexion_mysql->query("SELECT * FROM departamento_$tablaList WHERE departamento = {$descripcion['id']}");
    $respuesta["array_list"] = $q->fetch_all(MYSQLI_ASSOC);
}

echo json_encode($respuesta);
exit;


// =======================================================
// FUNCIONES IA (OLLAMA + QWEN2.5:7B)
// =======================================================
function procesarConsultaConIA($consulta, $departamentos, $categoriasPermitidas){

    $listaDepartamentos = implode(", ", $departamentos);
    $listaCategorias = implode(", ", $categoriasPermitidas);

    $prompt = "
        Eres un analizador de consultas en español (Colombia). Debes responder en MENOS de 30 segundos.

        Tarea:
        Dada una consulta del usuario, debes:
        1) Corregir ortografía y tildes sin cambiar el significado.
        2) Detectar la categoría (intención) usando sinónimos.
        - SOLO puedes devolver UNA categoría de esta lista exacta:
        $listaCategorias
        - Si no puedes clasificar, devuelve categoria como cadena vacía \"\".
        3) Detectar el departamento de Colombia SOLO si aparece explícitamente en la consulta.
        - SOLO puedes devolver un departamento de esta lista exacta:
        $listaDepartamentos
        - No inventes departamentos.
        - No adivines por ciudades (ej: Cali, Valledupar, Bogotá).
        - Si no está en la consulta, devuelve departamento como \"\".

        Reglas obligatorias:
        - Tu salida debe ser ÚNICAMENTE un JSON válido.
        - No agregues texto extra.
        - No agregues comentarios.
        - No uses markdown.
        - Las claves deben ser exactamente:
        departamento, categoria, consulta_corregida

        Formato exacto de salida:
        {
        \"departamento\": \"...\",
        \"categoria\": \"...\",
        \"consulta_corregida\": \"...\"
        }

        Ejemplos:
        Entrada: \"rios de cali\"
        Salida:
        { \"departamento\": \"\", \"categoria\": \"\", \"consulta_corregida\": \"ríos de Cali\" }

        Entrada: \"cabecera de antioquia\"
        Salida:
        { \"departamento\": \"antioquia\", \"categoria\": \"capital\", \"consulta_corregida\": \"cabecera de Antioquia\" }

        Entrada: \"gatronomia del cesar\"
        Salida:
        { \"departamento\": \"cesar\", \"categoria\": \"gastronomia\", \"consulta_corregida\": \"gastronomía del Cesar\" }

        Consulta del usuario:
        \"$consulta\"
    ";


    $data = [
        "model" => "qwen2.5:7b",
        "prompt" => $prompt,
        "stream" => false,
        "temperature" => 0.1,
        "num_predict" => 200-400
    ];

    $ch = curl_init("http://localhost:11434/api/generate");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $response = curl_exec($ch);

    if(curl_errno($ch)){
        return [
            "success" => false,
            "mensaje" => "Error conectando con Ollama: " . curl_error($ch)
        ];
    }

    curl_close($ch);

    $json = json_decode($response, true);
    $texto = $json["response"] ?? "";

    // El modelo a veces devuelve texto extra, entonces extraemos el JSON
    $texto = trim($texto);
    $inicio = strpos($texto, "{");
    $fin = strrpos($texto, "}");

    if($inicio === false || $fin === false){
        return [
            "success" => false,
            "mensaje" => "La IA no devolvió un JSON válido."
        ];
    }

    $soloJson = substr($texto, $inicio, $fin - $inicio + 1);
    $obj = json_decode($soloJson, true);

    if(!$obj){
        return [
            "success" => false,
            "mensaje" => "No se pudo parsear JSON de IA.",
        ];
    }

    if(!isset($obj["departamento"]) || !isset($obj["categoria"])){
        return [
            "success" => false,
            "mensaje" => "JSON incompleto devuelto por IA.",
        ];
    }

    $ejemplosValidos = "
        <ul style='margin-left: 20px !important; list-style: inside !important;'>
            <li><strong>capital de antioquia</strong></li>
            <li><strong>comunidades indigenas de la Guajira</strong></li>
            <li><strong>comida tipica de Boyaca</strong></li>
            <li><strong>relieve del Huila</strong></li>
            <li><strong>poblacion del Meta</strong></li>
            <li><strong>historia de Córdoba</strong></li>
            <li><strong>fiestas del Cesar</strong></li>
            <li><strong>sitios turisticos de Cundinamarca</strong></li>
            <li><strong>personajes importantes de Nariño</strong></li>
        </ul>
    ";

    if($obj["departamento"] === ""){
        return [
            "success" => false,
            "mensaje" => "No se puede procesar porque no ha suministrado el nombre del departamento, para una mejor consulta, puedes tomar como ejemplo: $ejemplosValidos",
        ];
    }

    if($obj["categoria"] === ""){
        return [
            "success" => false,
            "mensaje" => "No se puede procesar porque no ha suministrado la categoria, para una mejor consulta, puedes tomar como ejemplo: $ejemplosValidos",
        ];
    }

    return [
        "success" => true,
        "departamento" => $obj["departamento"],
        "categoria" => $obj["categoria"],
        "consulta_corregida" => $obj["consulta_corregida"] ?? $consulta
    ];
}
