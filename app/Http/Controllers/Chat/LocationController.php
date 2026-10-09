<?php

namespace App\Http\Controllers\Chat;

use App\Chat\ChatFeatures;
use App\Chat\PostMessage;
use App\Enums\ChatMessageType;
use App\Http\Controllers\Chat\Concerns\ResolvesChatMember;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Formation;
use App\Support\GoogleMaps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Partage d'un lieu dans un groupe (interrupteur « Partage de localisation ») : position actuelle du téléphone
 * ou lien Google Maps collé, avec un nom et une adresse facultatifs. Aucune carte intégrée ni clé d'API.
 */
class LocationController extends Controller
{
    use ResolvesChatMember;

    public function store(Request $request, Formation $formation, PostMessage $post): JsonResponse
    {
        abort_unless(ChatFeatures::location(), 404);

        $viewer = $this->viewer($request, $formation);

        if (! $viewer->canPost()) {
            throw ValidationException::withMessages(['location' => (string) $viewer->postBlockedReason()]);
        }

        $request->merge(['maps_url' => GoogleMaps::normalize($request->string('maps_url')->toString()) ?? $request->input('maps_url')]);

        $data = $request->validate([
            'mode' => ['required', Rule::in(['position', 'link'])],
            'latitude' => ['exclude_unless:mode,position', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['exclude_unless:mode,position', 'required', 'numeric', 'between:-180,180'],
            'maps_url' => ['exclude_unless:mode,link', 'required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! GoogleMaps::isValid($value)) {
                    $fail(GoogleMaps::invalidMessage());
                }
            }],
            'name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:200'],
            'reply_to_id' => ['nullable', 'integer'],
        ], [
            'mode.required' => 'Choisissez « Ma position actuelle » ou « Coller un lien Google Maps ».',
            'mode.in' => 'Choisissez « Ma position actuelle » ou « Coller un lien Google Maps ».',
            'latitude.required' => 'Position introuvable : touchez de nouveau « Ma position actuelle ».',
            'latitude.*' => 'Position invalide : touchez de nouveau « Ma position actuelle ».',
            'longitude.required' => 'Position introuvable : touchez de nouveau « Ma position actuelle ».',
            'longitude.*' => 'Position invalide : touchez de nouveau « Ma position actuelle ».',
            'maps_url.required' => 'Collez le lien Google Maps du lieu.',
            'name.max' => 'Nom du lieu trop long : 120 caractères au maximum.',
            'address.max' => 'Adresse trop longue : 200 caractères au maximum.',
        ]);

        $name = self::line($data['name'] ?? null);
        $address = self::line($data['address'] ?? null);
        $position = $data['mode'] === 'position';
        $latitude = $position ? (float) $data['latitude'] : null;
        $longitude = $position ? (float) $data['longitude'] : null;
        $url = $position ? GoogleMaps::coordinatesUrl((float) $latitude, (float) $longitude) : (string) $data['maps_url'];

        $message = $post->handle(
            $viewer->group,
            $viewer->member,
            $viewer->user,
            ChatMessageType::Location,
            // Texte court gardé dans le message : aperçu des notifications et de la liste des groupes.
            $name ?? $address,
            replyToId: isset($data['reply_to_id']) ? (int) $data['reply_to_id'] : null,
            details: fn (ChatMessage $message) => $message->location()->create([
                'name' => $name,
                'address' => $address,
                'latitude' => $latitude === null ? null : GoogleMaps::coordinate($latitude),
                'longitude' => $longitude === null ? null : GoogleMaps::coordinate($longitude),
                'maps_url' => $url,
            ]),
        );

        return response()->json(['id' => $message->id], 201);
    }

    private static function line(mixed $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value === '' ? null : $value;
    }
}
