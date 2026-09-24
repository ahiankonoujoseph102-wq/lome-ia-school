<?php

namespace App\Http\Requests;

use App\Models\CourseModule;
use App\Models\Lesson;
use App\Video\VideoProviders;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulaire d'une leçon (écran admin « Contenu des formations »).
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $formationId = $this->formationId();

        return [
            'course_module_id' => ['required', 'integer', Rule::exists('course_modules', 'id')->where('formation_id', $formationId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'video_url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && VideoProviders::default()->parse($value) === null) {
                    $fail('Ce lien vidéo n\'est pas reconnu. Collez le lien de la vidéo YouTube (bouton « Partager » puis « Copier le lien »).');
                }
            }],
            'duration' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && Lesson::parseDuration($value) === null) {
                    $fail('Durée non reconnue. Exemples : 12:30, 1:05:00 ou 12.');
                }
            }],
            'is_preview' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'resources' => ['nullable', 'array', 'max:10'],
            'resources.*' => ['file', 'mimes:'.implode(',', self::RESOURCE_MIMES), 'max:'.self::RESOURCE_MAX_KB],
        ];
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
        ];
    }

    /**
     * Données prêtes à enregistrer (sans les fichiers).
     *
     * @return array<string, mixed>
     */
    public function lessonData(): array
    {
        $url = trim($this->string('video_url')->toString());
        $provider = VideoProviders::default();

        return [
            'course_module_id' => $this->integer('course_module_id'),
            'title' => trim($this->string('title')->toString()),
            'description' => trim($this->string('description')->toString()) ?: null,
            'video_provider' => $url !== '' ? $provider->key() : null,
            'video_id' => $url !== '' ? $provider->parse($url) : null,
            'duration_seconds' => Lesson::parseDuration($this->string('duration')->toString()),
            'is_preview' => $this->boolean('is_preview'),
            'is_published' => $this->boolean('is_published'),
        ];
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
