<?php

return [
    'resource_label' => 'Agendamento|Agendamentos',
    'list' => [
        'headers' => [
            'name' => 'Nome',
            'status' => 'Status',
            'scheduled_at' => 'Agendado para',
            'completed_at' => 'Concluído em',
            'published_at' => 'Publicado em',
            'created_at' => 'Criado em',
            'updated_at' => 'Atualizado em',
            'deleted_at' => 'Excluído em',
        ],
        'published_now' => 'Publicado',
        'filters' => [
            'scheduled' => 'Agendado (ainda não publicado)',
        ],
        'empty_state' => [
            'heading' => 'Agendamentos',
            'description' => 'Planeje e agende suas manutenções.',
        ],
        'actions' => [
            'record_update' => 'Registrar Atualização',
            'complete' => 'Concluir Manutenção',
            'complete_success' => ':name foi marcada como concluída.',
        ],
    ],
    'form' => [
        'name_label' => 'Nome',
        'message_label' => 'Mensagem',
        'scheduled_at_label' => 'Agendado para',
        'scheduled_at_helper' => 'Quando a janela de manutenção começa.',
        'completed_at_label' => 'Concluído em',
        'completed_at_helper' => 'Quando a janela de manutenção deve terminar.',
        'published_at_label' => 'Publicado em',
        'published_at_helper' => 'Agende a manutenção para aparecer na página de status em uma data futura. Fica oculto para todos até lá. Deixe em branco para publicar imediatamente.',
        'notify_subscribers_label' => 'Notificar os inscritos sobre esta manutenção.',
        'notifications_helper' => 'Enviar e-mail aos inscritos sobre esta manutenção programada e suas atualizações.',
        'meta_label' => 'Metadados',
        'add_component' => [
            'action_label' => 'Adicionar componente',
            'header' => 'Componentes afetados',
            'component_label' => 'Componente',
            'status_label' => 'Status durante a manutenção',
        ],
    ],
    'add_update' => [
        'new_update_label' => 'Nova atualização',
        'success_title' => 'Atualização registrada',
        'success_body' => 'Você publicou uma nova atualização em :name.',
        'form' => [
            'message_label' => 'Mensagem',
            'completed_at_label' => 'Concluído em',
        ],
    ],
    'status' => [
        'upcoming' => 'Próximo',
        'in_progress' => 'Em Andamento',
        'complete' => 'Concluído',
    ],
    'window' => [
        'duration' => 'Duração',
        'starts_at' => 'Início',
        'expected_end' => 'Previsão de término',
        'ended_at' => 'Término',
    ],
    'planned_maintenance_header' => 'Manutenção Planejada',
];
