<?php

namespace App\Http\Controllers\Student;

use App\Accounts\EmailConfirmation;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvatarProcessor;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private AvatarProcessor $avatars, private EmailConfirmation $confirmation) {}

    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        [$dialCode, $localNumber] = PhoneNumber::split((string) $user->phone);

        return view('site.student.profile', [
            'user' => $user,
            'dialCode' => $dialCode !== '' ? $dialCode : PhoneNumber::DEFAULT_DIAL_CODE,
            // Affiché par groupes de chiffres, comme on l'écrit en Afrique de l'Ouest : 90 12 34 56, 01 94 55 59 14.
            'localNumber' => $dialCode !== '' ? PhoneNumber::groupDigits($localNumber) : (string) $user->phone,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $input = $this->withNormalizedPhone($request->all());

        $validated = Validator::make($input, [
            ...$this->profileRules($user->id),
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'wants_newsletter' => ['nullable', 'boolean'],
        ], [
            'phone.unique' => 'Ce numéro WhatsApp est déjà utilisé par un autre compte.',
            'avatar.max' => 'La photo ne doit pas dépasser 4 Mo.',
        ])->validate();

        $user->fill([
            'name' => trim((string) $validated['name']),
            'email' => strtolower((string) $validated['email']),
            'phone' => $validated['phone'],
            'wants_newsletter' => $request->boolean('wants_newsletter'),
        ]);

        // Nouvelle adresse : elle doit être confirmée comme à l'inscription (code et bouton envoyés à la nouvelle adresse).
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $this->confirmation->ensureCanSend($user, 'email');
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $previous = $user->avatar_path;
            $user->avatar_path = $this->avatars->store($request->file('avatar'), $user->id);
            $this->avatars->delete($previous);
        }

        $user->save();

        if ($emailChanged) {
            $this->confirmation->send($user);

            return to_route('email-confirmation.show')->with('status', 'Votre profil a été mis à jour. Confirmez votre nouvelle adresse avec le code envoyé à '.$user->email.'.');
        }

        return to_route('student.profile')->with('status', 'Votre profil a été mis à jour.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->avatars->delete($user->avatar_path);
        $user->forceFill(['avatar_path' => null])->save();

        return to_route('student.profile')->with('status', 'Votre photo de profil a été supprimée.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validateWithBag('deleteAccount', [
            'password' => $this->currentPasswordRules(),
        ]);

        $this->avatars->delete($user->avatar_path);

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
