<?php

namespace App\Http\Requests;

use App\Models\CourseModule;
use App\Models\Lesson;
use App\Support\GoogleDrive;
use App\Support\PastedLink;
use App\Video\VideoProviders;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulaire d'une leçon (écran admin « Contenu des formations »).
 *
 * Un lien par fournisseur vidéo (videos[youtube], videos[drive]…) : celui choisi comme lecteur principal
 * est la vidéo de la leçon, un autre lien renseigné devient le lecteur de secours.
 */
class LessonRequest extends FormRequest
{
    public const array RESOURCE_MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'mp3'];

    public const int RESOURCE_MAX_KB = 20480;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * Liens nettoyés avant la vérification (caractères invisibles, texte autour, https manquant).
     */
    protected function prepareForValidation(): void
    {
        PastedLink::cleanRequest($this, ['videos.*', 'link_url']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $providers = VideoProviders::all();
        $rules = [
            'course_module_id' => ['required', 'integer', Rule::exists('course_modules', 'id')->where('formation_id', $this->formationId())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'videos' => ['nullable', 'array'],
            'primary_video' => ['nullable', 'string', Rule::in(array_keys($providers))],
            'duration' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && Lesson::parseDuration($value) === null) {
                    $fail('Durée non reconnue. Exemples : 12:30, 1:05:00 ou 12.');
                }
            }],
            'is_preview' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'resources' => ['nullable', 'array', 'max:10'],
            'resources.*' => ['file', 'mimes:'.implode(',', self::RESOURCE_MIMES), 'max:'.self::RESOURCE_MAX_KB],
            'link_name' => ['nullable', 'required_with:link_url', 'string', 'max:150'],
            'link_url' => ['nullable', 'required_with:link_name', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && ! GoogleDrive::isGoogleUrl($value)) {
                    $fail('Le lien Google Drive doit ressembler à https://drive.google.com/file/d/… ou https://docs.google.com/…. Dans Drive : « Partager », puis « Copier le lien ».');
                }
            }],
        ];

        foreach ($providers as $key => $provider) {
            $rules['videos.'.$key] = ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail) use ($provider): void {
                if (is_string($value) && trim($value) !== '' && $provider->parse($value) === null) {
                    $fail($provider->invalidLinkMessage($value));
                }
            }];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Donnez un titre à la leçon.',
            'course_module_id.required' => 'Choisissez le module de la leçon.',
            'course_module_id.exists' => 'Choisissez un module de cette formation.',
            'resources.*.mimes' => 'Fichier refusé : formats acceptés PDF, images, Word, Excel, PowerPoint, ZIP, TXT, CSV, MP3.',
            'resources.*.max' => 'Chaque fichier doit faire moins de '.intdiv(self::RESOURCE_MAX_KB, 1024).' Mo.',
            'resources.*.uploaded' => 'Un fichier n\'a pas pu être envoyé. Réessayez avec un fichier plus léger.',
            'resources.max' => '10 fichiers au maximum à la fois.',
            'link_name.required_with' => 'Donnez un nom au lien Google Drive (ex. « Support de cours »).',
            'link_url.required_with' => 'Collez le lien Google Drive de la ressource.',
        ];
    }

    /**
     * Données prêtes à enregistrer (sans les fichiers ni les liens de ressources).
     *
     * @return array<string, mixed>
     */
    public function lessonData(): array
    {
        $videos = [];

        foreach (VideoProviders::all() as $key => $provider) {
            $link = trim($this->string('videos.'.$key)->toString());
            $id = $link !== '' ? $provider->parse($link) : null;

            if ($id !== null) {
                $videos[$key] = $id;
            }
        }

        // Lecteur principal : celui choisi s'il est renseigné, sinon le premier lien renseigné.
        $primary = $this->string('primary_video')->toString();
        $primary = array_key_exists($primary, $videos) ? $primary : array_key_first($videos);
        $backup = array_key_first(array_diff_key($videos, [(string) $primary => true]));

        return [
            'course_module_id' => $this->integer('course_module_id'),
            'title' => trim($this->string('title')->toString()),
            'description' => trim($this->string('description')->toString()) ?: null,
            'video_provider' => $primary,
            'video_id' => $primary !== null ? $videos[$primary] : null,
            'backup_video_provider' => $backup,
            'backup_video_id' => $backup !== null ? $videos[$backup] : null,
            'duration_seconds' => Lesson::parseDuration($this->string('duration')->toString()),
            'is_preview' => $this->boolean('is_preview'),
            'is_published' => $this->boolean('is_published'),
        ];
    }

    /**
     * Lien Google Drive ajouté comme ressource, ou null.
     *
     * @return array{name: string, url: string}|null
     */
    public function linkResource(): ?array
    {
        $url = trim($this->string('link_url')->toString());

        return $url === '' ? null : ['name' => trim($this->string('link_name')->toString()), 'url' => $url];
    }

    private function formationId(): int
    {
        $module = $this->route('module');
        $lesson = $this->route('lesson');

        if ($module instanceof CourseModule) {
            return $module->formation_id;
        }

        return $lesson instanceof Lesson ? $lesson->module->formation_id : 0;
    }
}
