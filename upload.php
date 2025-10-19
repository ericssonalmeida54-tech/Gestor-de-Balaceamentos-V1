<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$response = ['status' => 'error', 'message' => 'Nenhum arquivo enviado.'];

if (isset($_FILES['file'], $_POST['type'], $_POST['modelName'])) {
    $type = $_POST['type']; // 'layout' ou 'process'
    $modelName = preg_replace("/[^a-zA-Z0-9_.-]/", "", $_POST['modelName']); // Sanitize model name
    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'Erro no upload do arquivo. Código: ' . $file['error'];
        echo json_encode($response);
        exit;
    }

    $fileType = mime_content_type($file['tmp_name']);
    if ($fileType !== 'application/pdf') {
        $response['message'] = 'Tipo de arquivo inválido. Apenas PDF é permitido.';
        echo json_encode($response);
        exit;
    }
    
    $targetDir = ($type === 'layout') ? "layouts/" : "processes/";
    
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            $response['message'] = "Falha ao criar o diretório de destino.";
            echo json_encode($response);
            exit;
        }
    }
    
    $fileName = $modelName . '.pdf';
    $targetPath = $targetDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        require_once "config.php";
        $flagField = '';
        if ($type === 'layout') {
            $flagField = 'hasLayoutPdf';
        } elseif ($type === 'process') {
            $flagField = 'hasProcessPdf';
        }

        if ($flagField) {
            $sql = "UPDATE operations SET modelInfo = JSON_SET(modelInfo, '$.{$flagField}', true) WHERE model = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $_POST['modelName']);
                $stmt->execute();
                $stmt->close();
            }
        }
        $conn->close();
        
        $response = [
            'status' => 'success',
            'message' => 'Arquivo carregado com sucesso.',
            'filePath' => $targetPath
        ];
    } else {
        $response['message'] = 'Falha ao mover o arquivo para o diretório de destino.';
    }
} else {
    $response['message'] = 'Dados POST incompletos.';
}

echo json_encode($response);
?>

