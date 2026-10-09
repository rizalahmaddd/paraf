<?php

use App\Enums\FieldType;
use App\Enums\SigningOrderMode;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\User;
use App\Services\DocumentStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Login sebagai user ber-role tertentu beserta izin bawaannya, supaya tiap test tidak menulis
 * ulang setup role/login.
 */
function actingAsRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(seededRole($role));
    test()->actingAs($user);

    return $user;
}

/**
 * Peran beserta izin bawaannya dari PermissionSeeder, seperti hasil seeding di aplikasi nyata.
 * Superadmin dilewati: aksesnya lewat Gate::before, bukan daftar izin.
 */
function seededRole(string $name): Role
{
    $role = Role::findOrCreate($name, 'web');
    $permissions = PermissionSeeder::DEFAULT_ROLE_PERMISSIONS[$name] ?? [];

    if ($permissions !== ['*'] && $permissions !== []) {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $role->givePermissionTo($permissions);
    }

    return $role;
}

/**
 * Same as actingAsRole(), but authenticated through a Sanctum token for the mobile API.
 */
function apiActingAs(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(seededRole($role));
    Sanctum::actingAs($user);

    return $user;
}

function actingAsAdmin(): User
{
    return actingAsRole('admin');
}

function actingAsSuperAdmin(): User
{
    return actingAsRole('superadmin');
}

/**
 * Real PDF bytes rendered with dompdf, optionally password-protected.
 */
function samplePdf(int $pages = 1, ?string $userPassword = null): string
{
    $html = collect(range(1, $pages))
        ->map(fn (int $page) => "<h1>Halaman {$page}</h1><p>Isi perjanjian kerja sama.</p>")
        ->implode('<div style="page-break-after: always;"></div>');

    $pdf = Pdf::loadHTML($html)->setPaper('a4');

    if ($userPassword !== null) {
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->get_cpdf()->setEncryption($userPassword, 'owner-secret');
    }

    return $pdf->output();
}

function samplePngDataUrl(): string
{
    $image = imagecreatetruecolor(200, 60);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imageline($image, 10, 40, 190, 20, imagecolorallocate($image, 17, 24, 39));
    ob_start();
    imagepng($image);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

/**
 * A sent document with a real (encrypted) PDF in storage. Returns the document plus the plain
 * link token of every signer, keyed by signer name.
 *
 * @param  list<array{name: string, email?: ?string, passcode?: string, fields?: list<FieldType>}>  $signers
 * @return array{0: Document, 1: array<string, string>}
 */
function sentDocument(array $signers = [['name' => 'Budi']], bool $sequential = false, ?User $owner = null): array
{
    $owner ??= User::factory()->create();
    $owner->assignRole(seededRole('owner'));
    $bytes = samplePdf();

    $document = Document::factory()->for($owner)->sent()->create([
        'signing_order_mode' => $sequential ? SigningOrderMode::Sequential : SigningOrderMode::Parallel,
        'original_hash_sha256' => hash('sha256', $bytes),
        'file_size' => strlen($bytes),
    ]);
    $path = "documents/{$document->id}/original.pdf";
    app(DocumentStorage::class)->put($path, $bytes);
    $document->update(['original_pdf_path' => $path]);
    $document->pages()->create(['page_number' => 1, 'width_pt' => 595.28, 'height_pt' => 841.89, 'rotation_degrees' => 0]);

    $tokens = [];
    foreach ($signers as $index => $definition) {
        $signer = $document->signers()->create([
            'name' => $definition['name'],
            'email' => array_key_exists('email', $definition) ? $definition['email'] : strtolower($definition['name']).'@example.test',
            'color_tag' => '#2563EB',
            'signing_order' => $index + 1,
            'passcode_hash' => isset($definition['passcode']) ? Hash::make($definition['passcode']) : null,
            'invited_at' => now(),
        ]);

        foreach ($definition['fields'] ?? [FieldType::Signature] as $position => $type) {
            DocumentField::factory()->type($type)->create([
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'y_ratio' => 0.1 + $position * 0.1,
                'label' => $type === FieldType::Text ? 'NIK' : null,
            ]);
        }

        $tokens[$definition['name']] = $signer->issueAccessToken();
    }

    return [$document->fresh(), $tokens];
}
