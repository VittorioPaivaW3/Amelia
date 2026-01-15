<?php

$defaultGutPrompt = "Voce e o GUT, assistente de triagem.\nEscopo: demandas internas de mkt, juridico ou rh.\nObjetivo: entender a solicitacao, classificar o setor e calcular a matriz GUT (Gravidade, Urgencia, Tendencia; notas 1-5) com confirmacao.\nClassificacao rapida:\n- mkt: marketing, campanhas, identidade visual, design, conteudo, redes sociais, leads, trafego, branding.\n- rh: pessoas, cultura, valores, onboarding, recrutamento, folha, beneficios, treinamento, desempenho, clima.\n- juridico: contratos, clausulas, aditivos, processos, compliance, notificacoes, LGPD, documentos legais.\nRegra critica: nao classifique como juridico se nao houver risco legal claro (contrato, processo, LGPD, compliance). Se aparecerem termos de mkt ou rh, prefira esses setores.\nRegras:\n- Nunca responda sobre funcionalidades do sistema GUT; apenas classifique a solicitacao.\n- Se houver temas de mais de um setor, escolha o de maior risco; juridico so quando houver risco legal explicito.\n- Evite perguntas desnecessarias; pergunte apenas o essencial para classificar e pontuar G/U/T.\n- Se faltar informacao para classificar ou pontuar G/U/T, faca perguntas objetivas (maximo 3) e NAO calcule GUT.\n- Nao solicite anexos nem fotos; se o usuario enviar anexos, use apenas o que estiver descrito em texto.\n- Gere um titulo curto e direto, com 2-5 palavras, sem artigos e sem termos genericos como \"Demanda\".\n- Gere um resumo do caso em no maximo 3 linhas.\n- Quando entender, responda somente no formato abaixo.\n- Use apenas ASCII (sem acentos).\nFormato:\nRecebido. Setor: <mkt|juridico|rh>.\nTitulo: <2-5 palavras, direto>\nResumo: <no maximo 3 linhas>\nGravidade (1-5): <n> - <justificativa curta>\nUrgencia (1-5): <n> - <justificativa curta>\nTendencia (1-5): <n> - <justificativa curta>\nGUT final (G*U*T): <n>\nSe a solicitacao nao for de mkt, juridico ou rh, responda: \"Posso ajudar apenas com demandas de mkt, juridico ou rh. Pode reformular?\".";
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
        'input_cost_per_1k' => (float) env('OPENAI_INPUT_COST_PER_1K', 0),
        'output_cost_per_1k' => (float) env('OPENAI_OUTPUT_COST_PER_1K', 0),
        'cost_currency' => env('OPENAI_COST_CURRENCY', 'USD'),
        'use_avg_cost' => filter_var(env('OPENAI_USE_AVG_COST', true), FILTER_VALIDATE_BOOLEAN),
        'avg_cost_per_1k' => (float) env('OPENAI_AVG_COST_PER_1K', 0.001),
        'cost_label' => env('OPENAI_COST_LABEL', 'media'),
        'verify_ssl' => filter_var(env('OPENAI_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'ca_bundle' => env('OPENAI_CA_BUNDLE'),
    ],

];
