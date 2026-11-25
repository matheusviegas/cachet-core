<?php

return [
    'resource_label' => 'Grupo|Grupos',
    'defaults' => [
        'administrators' => 'Administradores',
        'read_only' => 'Somente leitura',
    ],
    'form' => [
        'name_label' => 'Nome',
        'permissions_label' => 'Permissões',
        'permissions_helper' => 'Os membros podem fazer tudo o que seus grupos permitem. Gerenciar ou excluir um recurso também permite visualizá-lo.',
        'default_helper' => 'Grupos padrão seguem a lista de permissões e não podem ser alterados.',
    ],
    'list' => [
        'headers' => [
            'name' => 'Nome',
            'permissions' => 'Permissões',
            'users' => 'Membros',
        ],
    ],
    'users' => [
        'title' => 'Membros',
    ],
];
