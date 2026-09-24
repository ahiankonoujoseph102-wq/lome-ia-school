<?php

namespace App\Policies;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\User;

/**
 * Un achat, sa capture et son reçu ne sont visibles que par l'élève concerné et par l'admin.
 */
class PurchasePolicy
{
    public function view(User $user, Purchase $purchase): bool
    {
        return $user->isAdmin() || ($purchase->user_id !== null && $purchase->user_id === $user->id);
    }

    /**
     * Reçu : uniquement pour un paiement validé.
     */
    public function viewReceipt(User $user, Purchase $purchase): bool
    {
        return $this->view($user, $purchase) && $purchase->status === PurchaseStatus::Validated;
    }
}
