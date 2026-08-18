<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Keepsake;

final readonly class InstanceKeepsakeContribution
{
    /**
     * @param  array<string, string>  $snapshotFiles
     * @param  array<string, string>  $blueprintFiles
     * @param  list<array{path:string,mime_type:string,size:int,sha256:string,contents?:string,source:string}>  $artifacts
     * @param  array<string, int|string|bool>  $summary
     * @param  list<array{reason:string,reference:string}>  $omissions
     */
    public function __construct(
        public string $key,
        public int $snapshotSchemaVersion,
        public ?int $blueprintSchemaVersion,
        public array $snapshotFiles = [],
        public array $blueprintFiles = [],
        public array $artifacts = [],
        public array $summary = [],
        public array $omissions = [],
    ) {}
}
