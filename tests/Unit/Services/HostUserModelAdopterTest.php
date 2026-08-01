<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Services\Host\HostUserModelAdopter;

function pristineLaravelUserSource(): string
{
    return <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use PasskeyAuthenticatable, TwoFactorAuthenticatable;
}
PHP;
}

it('adopts a pristine Laravel user while preserving passkeys and two factor traits', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'x-change-user-');
    file_put_contents($path, pristineLaravelUserSource());

    $result = app(HostUserModelAdopter::class)->adoptPath($path);
    $adopted = file_get_contents($path);

    expect($result['status'])->toBe('adopted')
        ->and($result['changed'])->toBeTrue()
        ->and($result['backup_path'])->not->toBeNull()
        ->and($adopted)
        ->toContain('use LBHurtado\XChange\Auth\XChangeAuthenticatable;')
        ->toContain('class User extends XChangeAuthenticatable implements PasskeyUser')
        ->toContain('use PasskeyAuthenticatable, TwoFactorAuthenticatable;');

    @unlink($path);
    if (is_string($result['backup_path'])) {
        @unlink($result['backup_path']);
    }
});

it('is idempotent and refuses an unknown custom user hierarchy', function (): void {
    $files = new Filesystem;
    $adopter = new HostUserModelAdopter($files);
    $path = tempnam(sys_get_temp_dir(), 'x-change-user-');
    file_put_contents($path, str_replace(
        'use Illuminate\Foundation\Auth\User as Authenticatable;',
        'use LBHurtado\XChange\Auth\XChangeAuthenticatable;',
        str_replace(
            'extends Authenticatable',
            'extends XChangeAuthenticatable',
            pristineLaravelUserSource(),
        ),
    ));

    expect($adopter->adoptPath($path)['status'])->toBe('already_adopted');

    file_put_contents($path, str_replace(
        'extends Authenticatable',
        'extends CorporateUser',
        pristineLaravelUserSource(),
    ));

    expect(fn () => $adopter->adoptPath($path))
        ->toThrow(RuntimeException::class, 'customized beyond the safe automatic adoption pattern');

    @unlink($path);
});

it('supports a no-write adoption preview', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'x-change-user-');
    $source = pristineLaravelUserSource();
    file_put_contents($path, $source);

    $result = app(HostUserModelAdopter::class)->adoptPath($path, commit: false);

    expect($result['status'])->toBe('would_adopt')
        ->and($result['changed'])->toBeFalse()
        ->and(file_get_contents($path))->toBe($source);

    @unlink($path);
});
