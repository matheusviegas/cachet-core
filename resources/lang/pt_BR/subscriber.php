<?php

return [
    'resource_label' => 'Inscrito|Inscritos',
    'list' => [
        'headers' => [
            'email' => 'Email',
            'verify_code' => 'Código de verificação',
            'global' => 'Global',
            'phone_number' => 'Número de telefone',
            'slack_webhook_url' => 'URL do Webhook do Slack',
            'verified_at' => 'Verificado em',
            'created_at' => 'Criado em',
            'updated_at' => 'Atualizado em',
        ],
        'empty_state' => [
            'heading' => 'Inscritos',
            'description' => 'Inscritos são pessoas que se cadastraram na sua página de status para receber notificações.',
        ],
        'actions' => [
            'verify_label' => 'Verificar',
            'verify_success' => ':email foi verificado.',
            'resend_verification_label' => 'Reenviar verificação',
        ],
    ],
    'resend_verification' => [
        'success_title' => 'E-mail de verificação enviado',
        'success_body' => 'Um novo e-mail de verificação foi enviado para :email.',
    ],
    'form' => [
        'email_label' => 'Email',
        'verify_code_label' => 'Código de verificação',
        'verified_at_label' => 'Verificado em',
        'meta_label' => 'Metadados',
        'global_label' => 'Global',
    ],
    'overview' => [
        'total_subscribers_label' => 'Total de Inscritos',
        'verified_subscribers_description' => ':count verificados',
    ],
    'status_page' => [
        'subscribe' => [
            'title' => 'Receber atualizações',
            'heading' => 'Receber atualizações',
            'description' => 'Receba um e-mail sempre que um incidente for registrado ou uma manutenção for programada.',
            'consent' => 'Enviaremos um e-mail de confirmação. Você pode cancelar a inscrição a qualquer momento.',
            'back' => 'Voltar para a página de status',
            'button_label' => 'Inscrever-se',
            'subscribed_heading' => 'Verifique seu e-mail',
            'subscribed_body' => 'Se este endereço ainda não estiver inscrito, você receberá um e-mail com um link para confirmar sua inscrição.',
            'verified_heading' => 'Inscrição confirmada',
            'verified_body' => 'A partir de agora você receberá as atualizações de status por e-mail.',
            'unsubscribed_heading' => 'Sua inscrição foi cancelada',
            'unsubscribed_body' => 'Você não receberá mais atualizações de status por e-mail. Você pode se inscrever novamente a qualquer momento.',
        ],
        'unsubscribe' => [
            'title' => 'Cancelar inscrição',
            'heading' => 'Cancelar o recebimento de atualizações?',
            'body' => ':email não receberá mais atualizações de status.',
            'button' => 'Cancelar inscrição',
            'cancel' => 'Manter minha inscrição',
        ],
        'email_label' => 'Endereço de e-mail',
        'email_placeholder' => 'voce@exemplo.com',
        'subscribe_button' => 'Inscrever-se',
    ],
    'mail' => [
        'unsubscribe' => 'Cancelar inscrição',
        'new_incident' => [
            'subject' => 'Novo incidente: :incident',
            'button' => 'Ver incidente',
        ],
        'incident_updated' => [
            'subject' => 'Incidente atualizado: :incident',
            'button' => 'Ver incidente',
        ],
        'new_schedule' => [
            'subject' => 'Manutenção programada: :schedule',
            'scheduled_for' => 'Programada para',
            'button' => 'Ver manutenção',
        ],
        'schedule_updated' => [
            'subject' => 'Manutenção atualizada: :schedule',
            'updated_at' => 'Atualizada em',
            'button' => 'Ver manutenção',
        ],
        'schedule_rescheduled' => [
            'subject' => 'Manutenção reprogramada: :schedule',
            'previously' => 'Anteriormente',
            'now' => 'Agora programada para',
            'button' => 'Ver manutenção',
        ],
        'schedule_completed' => [
            'subject' => 'Manutenção concluída: :schedule',
            'completed_at' => 'Concluída em',
            'body' => 'A manutenção programada foi concluída e todos os serviços afetados devem estar funcionando normalmente.',
            'button' => 'Ver manutenção',
        ],
        'verify' => [
            'subject' => 'Confirme sua inscrição',
            'heading' => 'Confirme sua inscrição',
            'body' => 'Você está recebendo este e-mail porque alguém se inscreveu para receber atualizações de status de :app. Confirme sua inscrição para começar a receber as atualizações.',
            'button' => 'Confirmar inscrição',
            'ignore' => 'Se você não se inscreveu em :app, pode ignorar este e-mail com segurança.',
        ],
    ],
];
