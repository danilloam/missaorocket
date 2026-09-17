<?php
require_once 'config.php';

try {
    // 1. Força a coluna a ter o tamanho correto (255 caracteres)
    $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN senha VARCHAR(255) NOT NULL");
    echo "• Coluna 'senha' alterada para VARCHAR(255) com sucesso!<br>";

    // 2. Gera o hash perfeito direto pelo motor de PHP do seu servidor
    $nova_senha_hash = password_hash('123456', PASSWORD_DEFAULT);

    // 3. Atualiza o usuário administrador com este hash novo
    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = 'admin@sistema.com'");
    $stmt->execute([$nova_senha_hash]);

    if ($stmt->rowCount() > 0) {
        echo "• Senha do administrador atualizada com sucesso para: <b>123456</b><br><br>";
        echo "<a href='index.php'>Clique aqui para ir para a tela de login</a>";
    } else {
        echo "• O usuário admin@sistema.com não foi encontrado para atualização. Verifique se ele existe no banco.";
    }

} catch (Exception $e) {
    echo "Erro ao corrigir o banco: " . $e->getMessage();
}
?>