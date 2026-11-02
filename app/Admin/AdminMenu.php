<?php

namespace App\Admin;

use App\Models\User;

/**
 * Menu de l'administration, regroupé par thème. Les modérateurs ne voient que la modération des groupes
 * et les sessions (jamais les ventes, réglages, élèves, codes promo ni l'affiliation).
 */
class AdminMenu
{
    /**
     * @return list<array{label: string, icon: string, links: list<array{label: string, route: string, icon: string, count?: int, active: list<string>}>}>
     */
    public static function groups(User $user): array
    {
        if (! $user->isAdmin()) {
            return $user->isStaff() ? [
                ['label' => 'Communauté', 'icon' => 'chat-bubble-left-right', 'links' => [
                    ['label' => 'Messages signalés', 'route' => 'moderation.reports.index', 'icon' => 'flag', 'count' => AdminTodo::openReports(), 'active' => ['moderation.reports.*']],
                ]],
                ['label' => 'Formations', 'icon' => 'academic-cap', 'links' => [
                    ['label' => 'Sessions en direct et à Lomé', 'route' => 'admin.sessions.index', 'icon' => 'calendar-days', 'active' => ['admin.sessions.*']],
                ]],
            ] : [];
        }

        return [
            ['label' => 'Ventes', 'icon' => 'banknotes', 'links' => [
                ['label' => 'Paiements à vérifier', 'route' => 'admin.payments.index', 'icon' => 'banknotes', 'count' => AdminTodo::paymentsToVerify(), 'active' => ['admin.payments.*']],
                ['label' => 'Toutes les ventes', 'route' => 'admin.sales.index', 'icon' => 'receipt-percent', 'active' => ['admin.sales.*']],
                ['label' => 'Codes promo', 'route' => 'admin.promo-codes.index', 'icon' => 'ticket', 'active' => ['admin.promo-codes.*']],
                ['label' => 'Exports CSV', 'route' => 'admin.exports.index', 'icon' => 'arrow-down-tray', 'active' => ['admin.exports.*']],
            ]],
            ['label' => 'Formations', 'icon' => 'academic-cap', 'links' => [
                ['label' => 'Formations et prix', 'route' => 'admin.formations.index', 'icon' => 'academic-cap', 'active' => ['admin.formations.*']],
                ['label' => 'Contenu (modules et leçons)', 'route' => 'admin.content.index', 'icon' => 'play-circle', 'active' => ['admin.content.*', 'admin.lessons.*', 'admin.modules.*']],
                ['label' => 'Sessions en direct et à Lomé', 'route' => 'admin.sessions.index', 'icon' => 'calendar-days', 'count' => AdminTodo::refundsDue(), 'active' => ['admin.sessions.*']],
            ]],
            ['label' => 'Élèves', 'icon' => 'users', 'links' => [
                ['label' => 'Élèves', 'route' => 'admin.students.index', 'icon' => 'users', 'active' => ['admin.students.*']],
                ['label' => 'Envoi d\'e-mails', 'route' => 'admin.newsletters.index', 'icon' => 'envelope', 'active' => ['admin.newsletters.*']],
            ]],
            ['label' => 'Communauté', 'icon' => 'chat-bubble-left-right', 'links' => [
                ['label' => 'Groupes de discussion', 'route' => 'admin.chat.index', 'icon' => 'chat-bubble-left-right', 'active' => ['admin.chat.*']],
                ['label' => 'Messages signalés', 'route' => 'moderation.reports.index', 'icon' => 'flag', 'count' => AdminTodo::openReports(), 'active' => ['moderation.reports.*']],
                ['label' => 'Demandes de devis', 'route' => 'admin.quotes.index', 'icon' => 'document-text', 'count' => AdminTodo::quotesToAnswer(), 'active' => ['admin.quotes.*']],
                ['label' => 'Demandes transmises', 'route' => 'admin.assistant.handoffs.index', 'icon' => 'inbox-arrow-down', 'count' => AdminTodo::handoffsToAnswer(), 'active' => ['admin.assistant.handoffs.*']],
                ['label' => 'Conversations d\'Ama', 'route' => 'admin.assistant.index', 'icon' => 'chat-bubble-oval-left-ellipsis', 'active' => ['admin.assistant.index', 'admin.assistant.show']],
                ['label' => 'Équipe de modération', 'route' => 'admin.moderators.index', 'icon' => 'user-group', 'active' => ['admin.moderators.*']],
            ]],
            ['label' => 'Affiliation', 'icon' => 'share', 'links' => [
                ['label' => 'Retraits à payer', 'route' => 'admin.affiliates.withdrawals', 'icon' => 'arrow-up-tray', 'count' => AdminTodo::withdrawalsToPay(), 'active' => ['admin.affiliates.withdrawals*']],
                ['label' => 'Affiliés', 'route' => 'admin.affiliates.index', 'icon' => 'share', 'active' => ['admin.affiliates.index', 'admin.affiliates.suspend', 'admin.affiliates.unsuspend']],
            ]],
            ['label' => 'Consultations', 'icon' => 'video-camera', 'links' => [
                ['label' => 'Rendez-vous', 'route' => 'admin.consultations.index', 'icon' => 'video-camera', 'count' => AdminTodo::consultationsToday(), 'active' => ['admin.consultations.index', 'admin.consultations.cancel', 'admin.consultations.attendance', 'admin.consultations.follow-up', 'admin.consultations.recommend']],
                ['label' => 'Disponibilités', 'route' => 'admin.consultations.availability', 'icon' => 'clock', 'active' => ['admin.consultations.availability', 'admin.consultations.settings', 'admin.consultations.hours.*', 'admin.consultations.blocks.*']],
            ]],
            ['label' => 'Réglages', 'icon' => 'cog-6-tooth', 'links' => [
                ['label' => 'Réglages du site', 'route' => 'admin.settings.index', 'icon' => 'cog-6-tooth', 'active' => ['admin.settings.*']],
                ['label' => 'Témoignages', 'route' => 'admin.testimonials.index', 'icon' => 'chat-bubble-bottom-center-text', 'active' => ['admin.testimonials.*']],
                ['label' => 'Journal d\'activité', 'route' => 'admin.activity.index', 'icon' => 'clipboard-document-list', 'active' => ['admin.activity.*']],
            ]],
        ];
    }

    /**
     * Total « à traiter » affiché sur le bouton du menu.
     */
    public static function todoTotal(User $user): int
    {
        if ($user->isAdmin()) {
            return AdminTodo::paymentsToVerify() + AdminTodo::withdrawalsToPay() + AdminTodo::openReports() + AdminTodo::consultationsToday() + AdminTodo::refundsDue() + AdminTodo::handoffsToAnswer() + AdminTodo::quotesToAnswer();
        }

        return $user->isStaff() ? AdminTodo::openReports() : 0;
    }
}
