<?php
// index.php
session_start(); // Inicia ou resume a sessão

// --- CONFIGURAÇÃO E CARREGAMENTO DA API KEY ---
$apiKey = getenv('GOOGLE_API_KEY'); // Tenta obter da variável de ambiente (RECOMENDADO)
// $apiKey = 'AIzaSyCXatd9oYlvxfS6HMcHHfynN2OTFIgY2f0';

if (empty($apiKey)) {
    // Se não encontrou na variável de ambiente, tenta incluir de um arquivo config.php
    // Este arquivo DEVE estar protegido ou fora do diretório web.
    $configFile = 'static/config.php'; // Ou o caminho absoluto se estiver fora do diretório web
    if (file_exists($configFile)) {
        include $configFile;
        if (isset($apiKeyFromConfig) && !empty($apiKeyFromConfig)) {
            $apiKey = $apiKeyFromConfig;
        }
    }
}

$errorMessage = '';
if (empty($apiKey)) {
    $errorMessage = "Erro Crítico: A chave GOOGLE_API_KEY não foi configurada no servidor nem no arquivo config.php. A aplicação não pode funcionar.";
    // Não faremos die() aqui ainda, para permitir que a página HTML de erro seja renderizada.
}

// --- DEFINIÇÕES DO MODELO E PERSONA ---
$modelName = 'gemini-1.5-flash-latest'; // Modelo recomendado
$systemInstructionText = 'Você é um psicanalista clínico nascido na fronteira do Rio Grande do Sul com o Uruguai, na cidade de Bagé e tem uma maneira de falar típica da região, bem similar a do personagem "Analista de Bagé" do escritor "Luis Fernando Verissimo". Seu nome é Azambuja e você é um grande conhecedor da obra de "Sigmund Freud", mas tende fortemente a associar os problemas de seus consultados à sua relação com seus genitores (pai ou mãe). Para você, todo e qualquer problema, afinal, é edipiano e a cura é um belo joelhaço. Porém, espere a interação com seu paciente desenrolar antes de expor a sua conclusão. Incentive o paciente a falar de si mesmo. Peça que ele se apresente, diga a sua idade, seu local de nascimento e quantos irmãos e irmãs têm. Tente inferir o sexo de seu paciente e, apenas se necessário, pergunte. A partir dessa interação inicial, busque construir uma boa conversa antes de expor a sua conclusão. Agora vamos começar. Sou uma pessoa que é sua paciente, em uma primeira consulta. Use expressões gauchescas como "tchê", "vivente", "guri/guria", "bah", "capaz", "de vereda", "peão", "prenda", "cusco", "chimarrão", "aspa", "cancha".';

// Inicializa o histórico da API e o histórico de exibição na sessão, se não existirem.
// 'api_chat_history' armazena o formato que a API Gemini espera.
// 'web_display_history' armazena um formato mais simples para renderizar no HTML.
if (!isset($_SESSION['api_chat_history'])) {
    $_SESSION['api_chat_history'] = array();
}
if (!isset($_SESSION['web_display_history'])) {
    $_SESSION['web_display_history'] = array();
}

// --- FUNÇÃO PARA CHAMAR A API GEMINI ---
function callGeminiAPI($apiKey, $modelName, $chatHistory, $systemInstruction) {
    if (empty($apiKey)) {
        return array('error' => 'API Key não configurada.');
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelName . ':generateContent?key=' . $apiKey;

    $payload = array(
        'contents' => $chatHistory,
        'systemInstruction' => array(
            'parts' => array(
                array('text' => $systemInstruction)
            )
        ),
 //       'generationConfig' => array(
 //           // Configurações opcionais de geração
 //           // 'temperature' => 0.7,
 //           // 'maxOutputTokens' => 1000,
 //       )
    );

    $jsonData = json_encode($payload);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
    ));
    // Para PHP 5 mais antigo, pode ser necessário desabilitar a verificação SSL
    // se o servidor não tiver os certificados CA atualizados.
    // ATENÇÃO: Isso é uma brecha de segurança. Tente atualizar os CAs primeiro.
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);


    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return array('error' => 'Erro de cURL: ' . $curlError);
    }

    if ($httpCode !== 200) {
        // Tenta decodificar a resposta de erro da API
        $errorDetails = json_decode($response, true);
        $apiErrorMessage = 'Erro da API Gemini (HTTP ' . $httpCode . '): ';
        if (isset($errorDetails['error']['message'])) {
            $apiErrorMessage .= $errorDetails['error']['message'];
        } else {
            $apiErrorMessage .= $response;
        }
        return array('error' => $apiErrorMessage);
    }

    $responseData = json_decode($response, true);

    // Extrai o texto da resposta do modelo
    // A estrutura pode variar um pouco; ajuste conforme necessário.
    // Geralmente é algo como $responseData['candidates'][0]['content']['parts'][0]['text']
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        return array('text' => $responseData['candidates'][0]['content']['parts'][0]['text']);
    } elseif (isset($responseData['promptFeedback']['blockReason'])) {
        $blockReason = $responseData['promptFeedback']['blockReason'];
        $safetyRatings = isset($responseData['promptFeedback']['safetyRatings']) ? json_encode($responseData['promptFeedback']['safetyRatings']) : 'N/A';
        return array('error' => 'A resposta foi bloqueada pela API. Razão: ' . $blockReason . '. Detalhes de segurança: ' . $safetyRatings);
    } else {
        // Resposta inesperada
        return array('error' => 'Formato de resposta inesperado da API. Resposta completa: ' . $response);
    }
}

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO E CHAT ---

// Ação de Nova Consulta
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    $_SESSION['api_chat_history'] = array();
    $_SESSION['web_display_history'] = array();
    // Redirecionar para limpar o parâmetro GET da URL e evitar re-reset no refresh
    header('Location: index.php');
    exit;
}

$flashMessage = '';
$flashCategory = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errorMessage)) {
    $userPrompt = isset($_POST['prompt']) ? trim($_POST['prompt']) : '';

    if (empty($userPrompt)) {
        $flashMessage = "Vivente, tu não disseste nada!";
        $flashCategory = "warning";
    } else {
        // Adiciona mensagem do usuário aos históricos
        $_SESSION['web_display_history'][] = array('speaker' => 'Você', 'text' => htmlspecialchars($userPrompt));
        $_SESSION['api_chat_history'][] = array('role' => 'user', 'parts' => array(array('text' => $userPrompt)));

        if (strtolower($userPrompt) === 'tchau') {
            $analystResponseText = "Tchau, e vê se te enxerga, vivente! Qualquer coisa, é só dar um grito. Volte sempre!";
            $_SESSION['web_display_history'][] = array('speaker' => 'Analista', 'text' => $analystResponseText);
            // Não adiciona "tchau" ao histórico da API para não confundir o modelo se o usuário disser "tchau" e depois continuar.
            // O reset é explícito via "Nova Consulta".
        } else {
            // Chama a API Gemini
            $apiResponse = callGeminiAPI($apiKey, $modelName, $_SESSION['api_chat_history'], $systemInstructionText);

            if (isset($apiResponse['text'])) {
                $analystResponseText = $apiResponse['text'];
                $_SESSION['web_display_history'][] = array('speaker' => 'Analista', 'text' => nl2br(htmlspecialchars($analystResponseText)));
                $_SESSION['api_chat_history'][] = array('role' => 'model', 'parts' => array(array('text' => $analystResponseText)));
            } else {
                // Erro da API
                $analystResponseText = "Mas que barbaridade! Deu um erro na comunicação com meus neurônios artificiais: " . htmlspecialchars($apiResponse['error']);
                $_SESSION['web_display_history'][] = array('speaker' => 'Analista', 'text' => $analystResponseText);
                // Não adiciona o erro ao histórico da API
            }
        }
        // Redirecionar após POST para evitar reenvio do formulário no refresh
        header('Location: index.php');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errorMessage)) {
    // Se houve POST mas a API Key não está configurada, mostra o erro.
    $flashMessage = $errorMessage;
    $flashCategory = "error";
}


// Se houver uma mensagem de erro global (ex: API Key faltando) e ainda não foi mostrada como flash,
// prepara para mostrar na área de erro principal.
$pageLevelErrorMessage = '';
if (!empty($errorMessage) && empty($flashMessage)) {
    $pageLevelErrorMessage = $errorMessage;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultório Virtual do Analista de Bagé (PHP)</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="page-wrapper">
        <header class="main-header">
            <img src="https://placehold.co/80x80/8B4513/FFFFFF?text=AB&font=lora" alt="Logo Analista de Bagé" class="logo">
            <h1>Consultório Virtual do Analista de Bagé</h1>
            <p class="subtitle">"Direto da fronteira, mais afiado que faca de açougueiro!"</p>
        </header>

        <?php if (!empty($pageLevelErrorMessage)): ?>
            <div class="error-container standalone-error">
                <h2>Mas que barbaridade, tchê! Deu chabu na cancela!</h2>
                <div class="error-icon">⚠️</div>
                <p class="error-message"><strong>Ocorreu um Erro Crítico:</strong></p>
                <p class="error-details"><?php echo htmlspecialchars($pageLevelErrorMessage); ?></p>
                <?php if (strpos($pageLevelErrorMessage, 'GOOGLE_API_KEY') !== false): ?>
                <p>Parece que a chave da API do Google não está configurada direito no servidor. <br>Verifique se a variável de ambiente <code>GOOGLE_API_KEY</code> foi exportada corretamente, ou se o arquivo <code>config.php</code> está configurado e acessível (mas protegido!).</p>
                <p>Exemplo (Linux/macOS): <code>export GOOGLE_API_KEY='SUA_CHAVE_AQUI'</code></p>
                <?php endif; ?>
                 <p>Sem a chave da API, o Analista não consegue prosear. Arrume isso e tente de novo!</p>
            </div>
        <?php else: // Só mostra o chat se não houver erro crítico de API Key ?>
            <main class="chat-container">
                <div class="chat-history" id="chat-history">
                    <?php if (!empty($flashMessage)): ?>
                        <div class="flash-message flash-<?php echo htmlspecialchars($flashCategory); ?>"><?php echo htmlspecialchars($flashMessage); ?></div>
                    <?php endif; ?>

                    <?php if (empty($_SESSION['web_display_history'])): ?>
                        <div class="message analyst-message">
                            <span class="speaker">Analista Azambuja:</span>
                            <span class="text">Buenas, vivente! Sou o Azambuja, direto de Bagé. Acomoda o esqueleto aí nesse divã virtual e desembucha, que nem água de sanga em dia de chuva forte! Mas antes de prosear sobre o que te rói a alma, te apresenta: como te chamas, quantos janeiros já viste passar lá pras bandas do teu nascimento, e quantos pilas ou prendas tens como mano(a)s? E te açodaha, que meu tempo é mais curto que coice de porco!</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($_SESSION['web_display_history'] as $message): ?>
                            <div class="message <?php echo ($message['speaker'] === 'Analista' ? 'analyst-message' : 'user-message'); ?>">
                                <span class="speaker"><?php echo htmlspecialchars($message['speaker']); ?>:</span>
                                <span class="text"><?php echo $message['text']; // Já foi sanitizado com htmlspecialchars ou nl2br(htmlspecialchars()) ao adicionar ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="POST" action="index.php" class="chat-input-form">
                    <input type="text" name="prompt" placeholder="Tua vez!" autofocus autocomplete="off">
                    <button type="submit" title="Enviar tua prosa">Manda Bala!</button>
                </form>
                <div class="action-buttons">
                    <a href="index.php?action=reset" class="button reset-button" title="Limpar a conversa e começar de novo">Nova Consulta</a>
                </div>
            </main>
        <?php endif; // Fim do else que esconde o chat se houver erro de API Key ?>

        <footer class="main-footer">
            <p>Inspirado no personagem de Luis Fernando Verissimo.</p>
            <p>&copy; <?php echo date('Y'); ?> - Este é um chatbot de entretenimento e não substitui aconselhamento profissional.</p>
            <p>"Não te acanhe, mas também não te atucana!"</p>
        </footer>
    </div>

    <script>
        const chatHistory = document.getElementById('chat-history');
        if (chatHistory) {
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }
    </script>
</body>
</html>
