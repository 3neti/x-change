<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final readonly class CloudRecipeRepository
{
    public const Schema = '3neti.x-change.cloud-recipe.v1';

    public function __construct(private Filesystem $files) {}

    /** @return array<string, mixed> */
    public function read(): array
    {
        $contents = $this->files->get(dirname(__DIR__, 3).'/resources/deployment/laravel-cloud.yaml');
        $recipe = Yaml::parse($contents);

        if (! is_array($recipe) || ($recipe['schema'] ?? null) !== self::Schema) {
            throw new RuntimeException('The package-owned Laravel Cloud recipe is invalid.');
        }

        return $recipe;
    }

    public function hash(): string
    {
        return hash('sha256', json_encode(
            $this->normalize($this->read()),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * @param  array<mixed>  $items
     * @return array<mixed>
     */
    private function normalize(array $items): array
    {
        if (! array_is_list($items)) {
            ksort($items);
        }

        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $items[$key] = $this->normalize($item);
            }
        }

        return $items;
    }
}
