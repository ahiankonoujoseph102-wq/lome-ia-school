<?php

namespace App\Http\Requests\Chat;

use App\Enums\ChatMessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nouveau message : texte, image, note vocale, vidéo courte ou fichier (liste blanche, aucun exécutable).
 * Les droits (membre du groupe, sourdine, mode annonces) sont vérifiés par le contrôleur.
 */
class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = ChatMessageType::tryFrom((string) $this->input('type'));
        $type = in_array($type, ChatMessageType::postable(), true) ? $type : null;

        return [
            // Lieu et sondage : formulaires à part (interrupteurs de l'admin), jamais par cette route.
            'type' => ['required', Rule::enum(ChatMessageType::class)->only(ChatMessageType::postable())],
            'body' => [$type === ChatMessageType::Text ? 'required' : 'nullable', 'string', 'max:'.config('chat.max_text_length')],
            'reply_to_id' => ['nullable', 'integer'],
            'email_members' => ['nullable', 'boolean'],
            'attachment' => $type === null || $type === ChatMessageType::Text ? ['prohibited'] : $this->attachmentRules($type),
            'duration' => $type === ChatMessageType::Voice
                ? ['required', 'integer', 'min:1', 'max:'.((int) config('chat.attachments.voice.max_seconds') + 2)]
                : ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<string>
     */
    private function attachmentRules(ChatMessageType $type): array
    {
        $key = $type->value;
        $rules = ['required', 'file', 'max:'.(int) config("chat.attachments.{$key}.max_kb")];

        return match ($type) {
            ChatMessageType::Image => [...$rules, 'mimes:'.implode(',', (array) config('chat.attachments.image.mimes'))],
            ChatMessageType::Voice => [...$rules, 'mimetypes:'.implode(',', (array) config('chat.attachments.voice.mimetypes'))],
            ChatMessageType::Video => [...$rules, 'mimetypes:'.implode(',', (array) config('chat.attachments.video.mimetypes'))],
            default => [
                ...$rules,
                'mimes:'.implode(',', (array) config('chat.attachments.file.mimes')),
                'extensions:'.implode(',', (array) config('chat.attachments.file.mimes')),
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mb = fn (string $type): int => intdiv((int) config("chat.attachments.{$type}.max_kb"), 1024);
        $type = (string) $this->input('type');

        return [
            'body.required' => 'Écrivez un message avant d\'envoyer.',
            'body.max' => 'Message trop long : '.config('chat.max_text_length').' caractères au maximum.',
            'attachment.required' => 'Choisissez un fichier à envoyer.',
            'attachment.uploaded' => 'Le fichier n\'a pas pu être envoyé. Vérifiez votre connexion ou choisissez un fichier plus léger.',
            'attachment.max' => match ($type) {
                'video' => 'Vidéo trop lourde : '.$mb('video').' Mo au maximum.',
                'voice' => 'Note vocale trop lourde.',
                'image' => 'Image trop lourde : '.$mb('image').' Mo au maximum.',
                default => 'Fichier trop lourd : '.$mb('file').' Mo au maximum.',
            },
            'attachment.mimes' => $type === 'image'
                ? 'Image refusée : formats acceptés JPG, PNG, WebP et GIF.'
                : 'Fichier refusé : formats acceptés PDF, Word, Excel, PowerPoint, ZIP et TXT.',
            'attachment.extensions' => 'Fichier refusé : formats acceptés PDF, Word, Excel, PowerPoint, ZIP et TXT.',
            'attachment.mimetypes' => $type === 'video'
                ? 'Vidéo refusée : formats acceptés MP4, MOV, WebM et 3GP.'
                : 'Note vocale illisible. Réessayez l\'enregistrement.',
            'attachment.prohibited' => 'Un message texte ne peut pas contenir de fichier.',
            'duration.required' => 'Durée de la note vocale manquante.',
            'duration.max' => 'Une note vocale dure '.intdiv((int) config('chat.attachments.voice.max_seconds'), 60).' minutes au maximum.',
            'type.required' => 'Type de message inconnu.',
            'type.enum' => 'Type de message inconnu.',
        ];
    }
}
