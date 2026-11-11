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
    case EmailConfirmed = 'email_confirmed';
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
    case HandoffHandled = 'handoff_handled';
    case HandoffReopened = 'handoff_reopened';
    case QuoteHandled = 'quote_handled';
    case QuoteReopened = 'quote_reopened';
    case FreeRegistrationUpdated = 'free_registration_updated';
    case ChatEmailAnnouncement = 'chat_email_announcement';
    case NewsletterSent = 'newsletter_sent';
    case NewsletterUpdated = 'newsletter_updated';
    case ScholarshipSettingsUpdated = 'scholarship_settings_updated';
    case ScholarshipValidated = 'scholarship_validated';
    case ScholarshipRefused = 'scholarship_refused';
    case ScholarshipRefunded = 'scholarship_refunded';

    public function label(): string
    {
        return match ($this) {
            self::PaymentValidated => 'Paiement validé',
            self::PaymentRejected => 'Paiement refusé',
            self::PaymentRefunded => 'Remboursement',
            self::ManualAccess => 'Accès manuel',
            self::UserBlocked => 'Compte bloqué',
            self::UserUnblocked => 'Compte débloqué',
            self::EmailConfirmed => 'E-mail confirmé manuellement',
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
            self::HandoffHandled => 'Demande transmise traitée',
            self::HandoffReopened => 'Demande transmise remise à traiter',
            self::QuoteHandled => 'Demande de devis traitée',
            self::QuoteReopened => 'Demande de devis remise à traiter',
            self::FreeRegistrationUpdated => 'Inscription gratuite modifiée',
            self::ChatEmailAnnouncement => 'Annonce envoyée par e-mail',
            self::NewsletterSent => 'E-mail groupé lancé',
            self::NewsletterUpdated => 'E-mail groupé : brouillon, pause, reprise ou annulation',
            self::ScholarshipSettingsUpdated => 'Bourse : réglages modifiés',
            self::ScholarshipValidated => 'Bourse : candidature validée',
            self::ScholarshipRefused => 'Bourse : candidature refusée',
            self::ScholarshipRefunded => 'Bourse : remboursement effectué',
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
