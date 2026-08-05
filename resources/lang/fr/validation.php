<?php

/*
|--------------------------------------------------------------------------
| Validation messages (French)
|--------------------------------------------------------------------------
|
| Only the keys the platform actually overrides live here; anything absent
| falls back to the framework's English messages. The password entries cover
| Illuminate\Validation\Rules\Password, whose failures are raw translation
| keys and therefore cannot be overridden per form.
|
*/

return [
    'password' => [
        'letters' => 'Le mot de passe doit contenir au moins une lettre.',
        'mixed' => 'Le mot de passe doit contenir au moins une lettre majuscule et une lettre minuscule.',
        'numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        'symbols' => 'Le mot de passe doit contenir au moins un caractère spécial.',
        'uncompromised' => 'Le mot de passe saisi est apparu dans une fuite de données. Veuillez en choisir un autre.',
    ],

    'min' => [
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],

    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',

    'attributes' => [
        'password' => 'mot de passe',
        'email' => 'adresse e-mail',
    ],
];
