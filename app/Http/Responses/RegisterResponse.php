<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Après l'inscription : page « Confirmez votre e-mail ». La page d'où venait le visiteur (par exemple le
 * récapitulatif de commande) reste en mémoire : il y revient dès que son adresse est confirmée.
 */
class RegisterResponse implements RegisterResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect()->route('email-confirmation.show');
    }
}
