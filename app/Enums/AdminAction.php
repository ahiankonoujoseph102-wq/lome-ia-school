<?php

namespace App\Enums;

/**
 * Actions enregistrées dans le journal d'activité de l'admin (table « admin_activities »).
 */
enum AdminAction: string
{
    case PaymentValidated = 'payment_validated';
    case PaymentRejected = 'payment_rejected';
    case PaymentRefunded = 'payment_refunded';
    case ManualAccess = 'manual_access';
    case UserBlocked = 'user_blocked';
    case UserUnblocked = 'user_unblocked';
    case RoleChanged = 'role_changed';
    case SettingsUpdated = 'settings_updated';
    case PaymentMethodsUpdated = 'payment_methods_updated';
    case AffiliateSettingsUpdated = 'affiliate_settings_updated';
    case TestimonialsUpdated = 'testimonials_updated';
    case FormationCreated = 'formation_created';
    case FormationUpdated = 'formation_updated';
    case PromoCodeCreated = 'promo_code_created';
    case PromoCodeUpdated = 'promo_code_updated';
    case PromoCodeDeleted = 'promo_code_deleted';
    case WithdrawalPaid = 'withdrawal_paid';
    case WithdrawalRejected = 'withdrawal_rejected';
    case AffiliateSuspended = 'affiliate_suspended';
    case AffiliateUnsuspended = 'affiliate_unsuspended';
    case SessionCreated = 'session_created';
    case SessionUpdated = 'session_updated';
    case SessionCancelled = 'session_cancelled';
    case ConsultationCancelled = 'consultation_cancelled';
    case Export = 'export';

    public function label(): string
    {
        return match ($this) {
            self::PaymentValidated => 'Paiement validé',
            self::PaymentRejected => 'Paiement refusé',
            self::PaymentRefunded => 'Remboursement',
            self::ManualAccess => 'Accès manuel',
            self::UserBlocked => 'Compte bloqué',
            self::UserUnblocked => 'Compte débloqué',
            self::RoleChanged => 'Rôle modifié',
            self::SettingsUpdated => 'Réglages modifiés',
            self::PaymentMethodsUpdated => 'Moyens de paiement modifiés',
            self::AffiliateSettingsUpdated => 'Réglages d\'affiliation modifiés',
            self::TestimonialsUpdated => 'Témoignages modifiés',
            self::FormationCreated => 'Formation créée',
            self::FormationUpdated => 'Formation modifiée',
            self::PromoCodeCreated => 'Code promo créé',
            self::PromoCodeUpdated => 'Code promo modifié',
            self::PromoCodeDeleted => 'Code promo supprimé',
            self::WithdrawalPaid => 'Retrait payé',
            self::WithdrawalRejected => 'Retrait refusé',
            self::AffiliateSuspended => 'Affilié suspendu',
            self::AffiliateUnsuspended => 'Affilié réactivé',
            self::SessionCreated => 'Session créée',
            self::SessionUpdated => 'Session modifiée',
            self::SessionCancelled => 'Session annulée',
            self::ConsultationCancelled => 'Consultation annulée',
            self::Export => 'Export CSV',
        };
    }

    /**
     * Changements sensibles : un e-mail d'alerte est envoyé à l'admin à chaque fois.
     */
    public function isSensitive(): bool
    {
        return in_array($this, [self::PaymentMethodsUpdated, self::AffiliateSettingsUpdated, self::RoleChanged], true);
    }
}
