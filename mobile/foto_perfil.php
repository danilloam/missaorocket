<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } 
require_once '../config.php'; 
// Verifica se existe usuário logado
if (empty($_SESSION['user_id'])) { http_response_code(403); exit; } $user_id = (int) $_SESSION['user_id']; 
// Busca SOMENTE a foto do usuário 
$stmt = $pdo->prepare(" SELECT foto FROM usuarios WHERE id = ? LIMIT 1 "); 
$stmt->execute([$user_id]); $foto = $stmt->fetchColumn(); 
// Se não existir foto, retorna 404 
if (empty($foto)) { http_response_code(404); exit; } 
// Define o tipo da imagem. // Como seu sistema atualmente trata as fotos como JPEG, // usamos image/jpeg. 
header('Content-Type: image/jpeg'); // Permite que o navegador mantenha a imagem em cache. // Isso evita buscar o BLOB novamente a cada abertura. 
header('Cache-Control: private, max-age=86400'); // Envia o BLOB diretamente para o navegador 
echo $foto; 
exit; ?>