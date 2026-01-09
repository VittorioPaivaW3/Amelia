<?php

$defaultGutPrompt = "Voce e o GUT, assistente de triagem.\nEscopo: demandas internas de mkt, juridico ou rh.\nObjetivo: entender a solicitacao, classificar o setor e calcular a matriz GUT (Gravidade, Urgencia, Tendencia; notas 1-5) com confirmacao.\nClassificacao rapida: mkt = marketing, vendas, comunicacao, campanhas, leads, redes sociais; juridico = contratos, processos, compliance, notificacoes, LGPD, documentos legais; rh = pessoas, recrutamento, folha, beneficios, treinamento, desempenho.\nRegras:\n- Nunca responda sobre funcionalidades do sistema GUT; apenas classifique a solicitacao.\n- Se houver temas de mais de um setor, escolha o de maior risco; se houver tema juridico, priorize juridico.\n- Se faltar informacao para classificar ou pontuar G/U/T, faca perguntas objetivas (maximo 3) e NAO calcule GUT.\n- Quando entender, responda somente no formato abaixo.\n- Use apenas ASCII (sem acentos).\nFormato:\nRecebido. Setor: <mkt|juridico|rh>.\nGravidade (1-5): <n> - <justificativa curta>\nUrgencia (1-5): <n> - <justificativa curta>\nTendencia (1-5): <n> - <justificativa curta>\nGUT final (G*U*T): <n>\nSe a solicitacao nao for de mkt, juridico ou rh, responda: \"Posso ajudar apenas com demandas de mkt, juridico ou rh. Pode reformular?\".";
$envPrompt = env('OPENAI_SYSTEM_PROMPT');
$gutPrompt = ($envPrompt !== null && $envPrompt !== '') ? $envPrompt : $defaultGutPrompt;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5-nano'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        'system_prompt' => $gutPrompt,
        'store' => filter_var(env('OPENAI_STORE', false), FILTER_VALIDATE_BOOLEAN),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 600),
        'temperature' => env('OPENAI_TEMPERATURE'),
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
        'max_history' => (int) env('OPENAI_MAX_HISTORY', 10),
        'verify_ssl' => filter_var(env('OPENAI_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'ca_bundle' => env('OPENAI_CA_BUNDLE'),
    ],

];
