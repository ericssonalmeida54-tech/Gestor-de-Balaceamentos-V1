<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Habilitar display de erros para debug (REMOVER EM PRODUÇÃO)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once "config.php"; // Garante que a conexão $conn está disponível

$response = ['status' => 'error', 'message' => 'Ação inválida ou não especificada.'];
$action = '';
$data = json_decode(file_get_contents("php://input"), true);

// Determina a ação
if (isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif (isset($data['action'])) {
    $action = $data['action'];
}

// Função auxiliar para buscar dados
function fetchData($conn, $table, $where = "") {
    $sql = "SELECT * FROM $table $where";
    $result = $conn->query($sql);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Decodifica campos JSON se existirem
            if (isset($row['modelInfo']) && is_string($row['modelInfo'])) {
                 $decoded = json_decode($row['modelInfo'], true);
                 $row['modelInfo'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : ['error' => 'Invalid JSON in modelInfo'];
            }
            if (isset($row['assignedOperators']) && is_string($row['assignedOperators'])) {
                $decoded = json_decode($row['assignedOperators'], true);
                $row['assignedOperators'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
            }
             if (isset($row['layoutPosition']) && is_string($row['layoutPosition'])) {
                $decoded = json_decode($row['layoutPosition'], true);
                $row['layoutPosition'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
             }
            $data[] = $row;
        }
        $result->free(); // Libera memória do resultado
    } else {
        // Log de erro se a query falhar
        error_log("SQL Error in fetchData ($table): " . $conn->error);
    }
    return $data;
}

switch ($action) {
    case 'login':
        if (isset($data['username']) && isset($data['password'])) {
            $username = $conn->real_escape_string($data['username']);
            $sql = "SELECT id, username, password, name, role FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($data['password'], $user['password'])) {
                        unset($user['password']); // Nunca retornar o hash da senha
                        $response = ['status' => 'success', 'user' => $user];
                    } else {
                        $response['message'] = 'Usuário ou senha inválidos.';
                    }
                } else {
                    $response['message'] = 'Usuário ou senha inválidos.';
                }
                $stmt->close();
            } else {
                 $response['message'] = 'Erro ao preparar a consulta de login.';
                 error_log("Login prepare failed: " . $conn->error);
            }
        } else {
             $response['message'] = 'Dados de login incompletos.';
        }
        break;

    case 'saveProcess':
        if (isset($data['operations']) && !empty($data['operations'])) {
            $conn->begin_transaction();
            try {
                // Prepara a inserção
                $sql = "INSERT INTO operations (operationId, model, sequence, description, machine, timeCentesimal, timeSeconds, operatorsReal, agrup, status, observation, assignedOperators, layoutPosition, modelInfo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception("Erro ao preparar a inserção: " . $conn->error);
                }

                foreach ($data['operations'] as $op) {
                    $assignedOpsJson = json_encode($op['assignedOperators'] ?? []);
                    $layoutPosJson = json_encode($op['layoutPosition'] ?? null);
                    $modelInfoJson = json_encode($op['modelInfo'] ?? []);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                         throw new Exception("Erro ao codificar JSON para a operação: " . $op['operationId'] . " - " . json_last_error_msg());
                    }
                    
                    // Tipos: s=string, i=integer, d=double/decimal
                     $stmt->bind_param("ssisddissssss",
                        $op['operationId'],
                        $op['modelInfo']['model'],
                        $op['sequence'],
                        $op['description'],
                        $op['machine'],
                        $op['timeCentesimal'],
                        $op['timeSeconds'],
                        $op['operatorsReal'],
                        $op['agrup'],
                        $op['status'],
                        $op['observation'],
                        $assignedOpsJson,
                        $layoutPosJson,
                        $modelInfoJson
                    );

                    if (!$stmt->execute()) {
                         throw new Exception("Erro ao executar inserção para opId " . $op['operationId'] . ": (" . $stmt->errno . ") " . $stmt->error);
                    }
                }
                $stmt->close();
                $conn->commit();
                $response = ['status' => 'success', 'message' => 'Balanceamento salvo com sucesso.'];
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'Erro ao salvar o balanceamento: ' . $e->getMessage();
                error_log("SaveProcess Error: " . $e->getMessage());
                if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
            }
        } else {
             $response['message'] = 'Nenhuma operação recebida para salvar.';
        }
        break;

    case 'getOperations':
        $whereClauses = [];
        $params = [];
        $types = "";

        if (isset($_GET['modelName'])) {
            $whereClauses[] = "model = ?";
            $params[] = $_GET['modelName'];
            $types .= "s";
        }
        if (isset($_GET['status']) && (!isset($_GET['userRole']) || $_GET['userRole'] != 'lideranca')) {
            $whereClauses[] = "status = ?";
            $params[] = $_GET['status'];
            $types .= "s";
        }
        if (isset($_GET['userRole']) && $_GET['userRole'] == 'lideranca') {
            $whereClauses[] = "status = 'aprovado'";
        }
        if (isset($_GET['badgeId'])) {
             $whereClauses[] = "JSON_SEARCH(assignedOperators, 'one', ?, NULL, '$[*].badgeId') IS NOT NULL";
             $params[] = $_GET['badgeId'];
             $types .= "s";
        }

        $sql = "SELECT * FROM operations";
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
         $sql .= " ORDER BY model, sequence";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
             $response['message'] = 'Erro ao preparar consulta de operações: ' . $conn->error;
             error_log("GetOperations prepare failed: " . $conn->error);
             break;
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $opsData = [];
            while($row = $result->fetch_assoc()){
                 if (isset($row['modelInfo']) && is_string($row['modelInfo'])) {
                    $decoded = json_decode($row['modelInfo'], true);
                    $row['modelInfo'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : ['error' => 'Invalid JSON in modelInfo'];
                 }
                 if (isset($row['assignedOperators']) && is_string($row['assignedOperators'])) {
                     $decoded = json_decode($row['assignedOperators'], true);
                     $row['assignedOperators'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
                 }
                  if (isset($row['layoutPosition']) && is_string($row['layoutPosition'])) {
                     $decoded = json_decode($row['layoutPosition'], true);
                     $row['layoutPosition'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
                  }
                 $opsData[] = $row;
            }
            $response = ['status' => 'success', 'data' => $opsData];
            $result->free();
        } else {
            $response['message'] = "Erro ao buscar operações: " . $stmt->error;
            error_log("GetOperations execute failed: (" . $stmt->errno . ") " . $stmt->error);
        }
        $stmt->close();
        break;

    // CORRIGIDO: Nome da ação para 'updateOperation' e removida a duplicação.
    case 'updateOperation':
        if (isset($data['opId'], $data['field'], $data['value'])) {
            $opId = $data['opId'];
            $field = $data['field'];
            $allowed_fields = ['observation', 'assignedOperators', 'layoutPosition'];

            if (in_array($field, $allowed_fields)) {
                $value = is_array($data['value']) ? json_encode($data['value']) : $data['value'];

                if (is_array($data['value']) && json_last_error() !== JSON_ERROR_NONE) {
                    $response['message'] = 'Valor JSON inválido para o campo ' . htmlspecialchars($field) . '.';
                    error_log("Invalid JSON for field '$field', opId: $opId. Data: " . print_r($data['value'], true));
                    break;
                }

                $sql = "UPDATE operations SET `{$conn->real_escape_string($field)}` = ? WHERE operationId = ?";
                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param("ss", $value, $opId);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                             $response = ['status' => 'success'];
                        } else {
                             $checkSql = "SELECT COUNT(*) as count FROM operations WHERE operationId = ?";
                             $checkStmt = $conn->prepare($checkSql);
                             $checkStmt->bind_param("s", $opId);
                             $checkStmt->execute();
                             $checkResult = $checkStmt->get_result()->fetch_assoc();
                             $checkStmt->close();

                             if ($checkResult['count'] == 0) {
                                 $response['message'] = "Erro: Operação com ID '" . htmlspecialchars($opId) . "' não foi encontrada.";
                                 error_log("Update failed: Operation ID not found - " . $opId);
                             } else {
                                 $response = ['status' => 'success', 'message' => 'Nenhuma alteração. Os dados podem já estar atualizados.'];
                             }
                        }
                    } else {
                        $response['message'] = "Erro SQL ao atualizar campo '$field': (" . $stmt->errno . ") " . $stmt->error;
                        error_log("SQL Error updating operation field '$field' (" . $stmt->errno . "): " . $stmt->error . " | opId: " . $opId);
                    }
                    $stmt->close();
                } else {
                     $response['message'] = 'Erro interno ao preparar a atualização.';
                     error_log("UpdateOperation prepare failed: " . $conn->error);
                }
            } else {
                $response['message'] = 'Campo (' . htmlspecialchars($field) . ') não permitido para atualização.';
            }
        } else {
             $response['message'] = 'Dados incompletos para atualizar (requer: opId, field, value).';
        }
        break;
        
    case 'saveLayoutPositions':
        if (isset($data['operations']) && is_array($data['operations'])) {
            $conn->begin_transaction();
            try {
                $sql = "UPDATE operations SET layoutPosition = ? WHERE operationId = ?";
                $stmt = $conn->prepare($sql);
                 if (!$stmt) {
                    throw new Exception("Erro ao preparar a atualização das posições: " . $conn->error);
                }

                foreach ($data['operations'] as $op) {
                     if (!isset($op['operationId'])) continue;

                    $layoutPos = json_encode($op['layoutPosition'] ?? null);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("Invalid JSON for layoutPosition, opId: " . $op['operationId']);
                        continue;
                    }

                    $stmt->bind_param("ss", $layoutPos, $op['operationId']);
                     if (!$stmt->execute()) {
                        error_log("Erro ao salvar layout para opId " . $op['operationId'] . ": (" . $stmt->errno . ") " . $stmt->error);
                     }
                }
                $stmt->close();
                $conn->commit();
                $response = ['status' => 'success', 'message' => 'Layout salvo com sucesso.'];
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'Falha crítica ao salvar o layout: ' . $e->getMessage();
                 error_log("SaveLayoutPositions Error: " . $e->getMessage());
                 if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
            }
        } else {
            $response['message'] = 'Dados de operações inválidos ou ausentes para salvar layout.';
        }
        break;

    case 'updateBrand':
        if(isset($data['modelName'], $data['brand'])) {
            $sql = "UPDATE operations SET modelInfo = JSON_SET(modelInfo, '$.brand', ?) WHERE model = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $data['brand'], $data['modelName']);
                if ($stmt->execute()) {
                    $response = ['status' => 'success'];
                } else {
                    $response['message'] = 'Erro SQL ao atualizar a marca: ' . $stmt->error;
                    error_log("UpdateBrand execute failed: (" . $stmt->errno . ") " . $stmt->error);
                }
                 $stmt->close();
            } else {
                 $response['message'] = 'Erro ao preparar a atualização da marca.';
                 error_log("UpdateBrand prepare failed: " . $conn->error);
            }
        } else {
            $response['message'] = 'Dados incompletos para atualizar marca (modelName, brand).';
        }
        break;

    case 'updateProcessStatus':
        if (isset($data['modelName'], $data['status'])) {
            $modelName = $data['modelName'];
            $status = $data['status'];
             $sql = "UPDATE operations SET status = ? WHERE model = ?";
             $stmt = $conn->prepare($sql);
             if ($stmt) {
                 $stmt->bind_param("ss", $status, $modelName);
                 if ($stmt->execute()) {
                     $response = ['status' => 'success'];
                 } else {
                     $response['message'] = "Erro SQL ao atualizar status do balanceamento: " . $stmt->error;
                     error_log("UpdateProcessStatus execute failed: (" . $stmt->errno . ") " . $stmt->error);
                 }
                 $stmt->close();
             } else {
                 $response['message'] = 'Erro ao preparar a atualização do status.';
                 error_log("UpdateProcessStatus prepare failed: " . $conn->error);
             }
        } else {
             $response['message'] = 'Dados incompletos para atualizar status (modelName, status).';
        }
        break;

    case 'deleteProcess':
        if (isset($data['modelName'])) {
            $modelName = $data['modelName'];
            $sql = "DELETE FROM operations WHERE model = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $modelName);
                 if ($stmt->execute()) {
                     $response = ['status' => 'success'];
                 } else {
                     $response['message'] = "Erro SQL ao excluir o balanceamento: " . $stmt->error;
                     error_log("DeleteProcess execute failed: (" . $stmt->errno . ") " . $stmt->error);
                 }
                 $stmt->close();
            } else {
                 $response['message'] = 'Erro ao preparar a exclusão do balanceamento.';
                 error_log("DeleteProcess prepare failed: " . $conn->error);
            }
        } else {
             $response['message'] = 'Nome do modelo ausente para exclusão.';
        }
        break;

    case 'getOperators':
        $where = "";
        if (isset($_GET['badgeId'])) {
            $badgeId = $conn->real_escape_string($_GET['badgeId']);
            $where = "WHERE badgeId = '$badgeId'";
        }
        $response = ['status' => 'success', 'data' => fetchData($conn, 'operators', $where . " ORDER BY name")];
        break;

    case 'getUsers':
        $users = fetchData($conn, 'users', "ORDER BY name");
        foreach ($users as &$user) {
            unset($user['password']);
        }
        $response = ['status' => 'success', 'data' => $users];
        break;

    case 'createUser':
        if (isset($data['name'], $data['username'], $data['password'], $data['role'])) {
            if (empty($data['name']) || empty($data['username']) || empty($data['password']) || empty($data['role'])) {
                 $response['message'] = 'Todos os campos são obrigatórios.';
                 break;
            }
             if (!in_array($data['role'], ['publico', 'lideranca', 'analista', 'gerente', 'admin'])) {
                 $response['message'] = 'Cargo inválido selecionado.';
                 break;
            }

            $sql = "INSERT INTO users (id, name, username, password, role) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
             if ($stmt) {
                 $id = 'user_' . bin2hex(random_bytes(8));
                 $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
                 $stmt->bind_param("sssss", $id, $data['name'], $data['username'], $passwordHash, $data['role']);

                 if($stmt->execute()){
                     $response = ['status' => 'success'];
                 } else {
                     if ($conn->errno == 1062) {
                         $response['message'] = 'Este nome de usuário já está em uso.';
                     } else {
                         $response['message'] = "Erro SQL ao criar usuário: " . $stmt->error;
                         error_log("CreateUser execute failed: (" . $stmt->errno . ") " . $stmt->error);
                     }
                 }
                 $stmt->close();
             } else {
                 $response['message'] = 'Erro ao preparar a criação do usuário.';
                 error_log("CreateUser prepare failed: " . $conn->error);
             }
        } else {
             $response['message'] = 'Dados incompletos para criar usuário.';
        }
        break;

    case 'updateUser':
        if (isset($data['id'], $data['name'], $data['role'])) {
             if (empty($data['name']) || empty($data['role'])) {
                 $response['message'] = 'Nome e Cargo são obrigatórios.';
                 break;
            }
             if (!in_array($data['role'], ['publico', 'lideranca', 'analista', 'gerente', 'admin'])) {
                 $response['message'] = 'Cargo inválido selecionado.';
                 break;
            }

            $sql = "UPDATE users SET name = ?, role = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
             if ($stmt) {
                 $stmt->bind_param("sss", $data['name'], $data['role'], $data['id']);
                 if($stmt->execute()){
                     $response = ($stmt->affected_rows > 0) ? ['status' => 'success'] : ['status' => 'success', 'message' => 'Nenhum dado alterado.'];
                 } else {
                     $response['message'] = "Erro SQL ao atualizar usuário: " . $stmt->error;
                      error_log("UpdateUser execute failed: (" . $stmt->errno . ") " . $stmt->error);
                 }
                  $stmt->close();
             } else {
                  $response['message'] = 'Erro ao preparar a atualização do usuário.';
                  error_log("UpdateUser prepare failed: " . $conn->error);
             }
        } else {
             $response['message'] = 'Dados incompletos para atualizar usuário (id, name, role).';
        }
        break;

    case 'deleteUser':
        if (isset($data['id'])) {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $data['id']);
                 if($stmt->execute()){
                      $response = ($stmt->affected_rows > 0) ? ['status' => 'success'] : ['status' => 'error', 'message' => 'Usuário não encontrado ou já excluído.'];
                 } else {
                      $response['message'] = "Erro SQL ao excluir usuário: " . $stmt->error;
                      error_log("DeleteUser execute failed: (" . $stmt->errno . ") " . $stmt->error);
                 }
                  $stmt->close();
            } else {
                 $response['message'] = 'Erro ao preparar a exclusão do usuário.';
                 error_log("DeleteUser prepare failed: " . $conn->error);
            }
        } else {
             $response['message'] = 'ID do usuário ausente para exclusão.';
        }
        break;

    default:
        $response['message'] = 'Ação inválida ou não especificada.';
        break;
}

$conn->close();
echo json_encode($response);
?>
