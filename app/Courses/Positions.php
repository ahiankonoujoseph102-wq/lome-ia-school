<?php

namespace App\Courses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Ordre des modules et des leçons : renumérotation et déplacement d'un cran vers le haut ou le bas.
 */
class Positions
{
    /**
     * Déplace l'élément d'un cran dans la liste ordonnée de ses voisins (même module ou même formation).
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $siblings  Liste ordonnée contenant l'élément.
     * @param  TModel  $item
     */
    public static function move(Collection $siblings, Model $item, string $direction): void
    {
        $ordered = $siblings->values()->all();
        $index = collect($ordered)->search(fn (Model $sibling): bool => $sibling->is($item));

        if ($index === false) {
            return;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target >= 0 && $target < count($ordered)) {
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
        }

        foreach ($ordered as $position => $sibling) {
            if ((int) $sibling->getAttribute('position') !== $position + 1) {
                $sibling->forceFill(['position' => $position + 1])->save();
            }
        }
    }
}
