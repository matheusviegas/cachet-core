<?php

return [
    'resource_label' => 'Usuário|Usuários',
    'profile_information_title' => 'Informações do perfil',
    'security_section_title' => 'Senha e segurança',
    'preferences_section_title' => 'Preferências',
    'list' => [
        'headers' => [
            'name' => 'Nome',
            'email' => 'Endereço de E-mail',
            'email_verified_at' => 'E-mail Verificado Em',
            'groups' => 'Grupos',
        ],
        'actions' => [
            'verify_email' => 'Verificar E-mail',
            'verify_email_success' => 'Um e-mail de verificação foi enviado para :email.',
            'reset_two_factor' => 'Redefinir autenticação em dois fatores',
            'reset_two_factor_confirmation' => 'Isso removerá a configuração da autenticação em dois fatores e os códigos de recuperação do usuário. Ele poderá entrar apenas com a senha até configurá-la novamente.',
            'reset_two_factor_success' => 'Autenticação em dois fatores redefinida.',
        ],
    ],
    'form' => [
        'name_label' => 'Nome',
        'email_label' => 'Endereço de E-mail',
        'password_label' => 'Senha',
        'password_confirmation_label' => 'Confirmar Senha',
        'preferred_locale' => 'Idioma Preferido',
        'preferred_locale_system_default' => 'Padrão do Sistema',
        'preferred_locale_helper' => 'O idioma do painel. Padrão do Sistema segue o idioma do seu navegador.',
        'groups_label' => 'Grupos',
        'groups_helper' => 'O usuário pode fazer tudo o que seus grupos permitem.',
    ],
];
