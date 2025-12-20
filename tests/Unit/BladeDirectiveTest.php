<?php

use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $seeder = new SampleDataSeeder();
    $seeder->run();

    $this->app->singleton('aauth', function ($app) {
        return new \AuroraWebSoftware\AAuth\AAuth(
            User::find(1),
            3
        );
    });
});

test('aauth blade directive compiles correctly', function () {
    $compiled = Blade::compileString("@aauth('test_permission')");

    expect($compiled)->toContain('<?php if(\AuroraWebSoftware\AAuth\Facades\AAuth::can(\'test_permission\')){ ?>');
});

test('endaauth blade directive compiles correctly', function () {
    $compiled = Blade::compileString('@endaauth');

    expect($compiled)->toBe('<?php } ?>');
});

test('aauth blade directive with permission renders content when user has permission', function () {
    $blade = "@aauth('create_something_for_organization')
    <div>User has permission</div>
@endaauth";

    $compiled = Blade::compileString($blade);
    ob_start();
    eval('?>'.$compiled);
    $output = ob_get_clean();

    expect(trim($output))->toContain('User has permission');
});

test('aauth blade directive hides content when user lacks permission', function () {
    $blade = "@aauth('non_existent_permission')
    <div>User has permission</div>
@endaauth";

    $compiled = Blade::compileString($blade);
    ob_start();
    eval('?>'.$compiled);
    $output = ob_get_clean();

    expect(trim($output))->toBe('');
});

test('aauth blade directive works with single quotes', function () {
    $blade = "@aauth('create_something_for_organization')
    <p>Content visible</p>
@endaauth";

    $compiled = Blade::compileString($blade);

    expect($compiled)->toContain("<?php if(\AuroraWebSoftware\AAuth\Facades\AAuth::can('create_something_for_organization')){ ?>");
    expect($compiled)->toContain('<?php } ?>');
});

test('aauth blade directive works with double quotes', function () {
    $blade = '@aauth("create_something_for_organization")
    <p>Content visible</p>
@endaauth';

    $compiled = Blade::compileString($blade);

    expect($compiled)->toContain('<?php if(\AuroraWebSoftware\AAuth\Facades\AAuth::can("create_something_for_organization")){ ?>');
    expect($compiled)->toContain('<?php } ?>');
});

test('aauth blade directive works with variable', function () {
    $blade = '@aauth($permission)
    <p>Content visible</p>
@endaauth';

    $compiled = Blade::compileString($blade);

    expect($compiled)->toContain('<?php if(\AuroraWebSoftware\AAuth\Facades\AAuth::can($permission)){ ?>');
    expect($compiled)->toContain('<?php } ?>');
});

test('nested aauth blade directives compile correctly', function () {
    $blade = "@aauth('permission1')
    <div>Outer content</div>
    @aauth('permission2')
        <div>Inner content</div>
    @endaauth
@endaauth";

    $compiled = Blade::compileString($blade);

    expect($compiled)->toContain("<?php if(\AuroraWebSoftware\AAuth\Facades\AAuth::can('permission1')){ ?>");
    expect($compiled)->toContain("<?php if(\AuroraWebSoftware\AAuth\Facades\AAuth::can('permission2')){ ?>");
});

test('aauth blade directive with html content renders correctly when permitted', function () {
    $blade = "@aauth('create_something_for_organization')
    <button class='btn'>Delete</button>
    <a href='/admin'>Admin Panel</a>
@endaauth";

    $compiled = Blade::compileString($blade);
    ob_start();
    eval('?>'.$compiled);
    $output = ob_get_clean();

    expect($output)->toContain('<button class=\'btn\'>Delete</button>')
        ->and($output)->toContain('<a href=\'/admin\'>Admin Panel</a>');
});

test('multiple aauth blocks in same template work independently', function () {
    $blade = "@aauth('create_something_for_organization')
    <div>First block</div>
@endaauth

@aauth('non_existent_permission')
    <div>Second block</div>
@endaauth

@aauth('create_something_for_organization')
    <div>Third block</div>
@endaauth";

    $compiled = Blade::compileString($blade);
    ob_start();
    eval('?>'.$compiled);
    $output = ob_get_clean();

    expect($output)->toContain('First block')
        ->and($output)->toContain('Third block')
        ->and($output)->not->toContain('Second block');
});
