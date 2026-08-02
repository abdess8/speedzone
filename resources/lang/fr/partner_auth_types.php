<?php

return [
    'BASIC' => 'Identifiant & Mot de passe (Basic Auth)',
    'BEARER' => 'Bearer Token (statique)',
    'API_KEY' => 'Clé API (header)',
    'LOGIN_TOKEN' => 'Endpoint login → Token',

    'descriptions' => [
        'BASIC' => 'Authentification HTTP Basic avec identifiant (Client ID) et mot de passe (Client Secret).',
        'BEARER' => 'Un token bearer statique envoyé dans l\'en-tête Authorization.',
        'API_KEY' => 'Une clé API envoyée dans un en-tête HTTP personnalisé (défaut : X-API-Key).',
        'LOGIN_TOKEN' => 'POST des identifiants vers l\'endpoint login, récupération du token (ex. data.token), puis Bearer pour toutes les requêtes API (Sendit : public_key + secret_key).',
    ],
];
