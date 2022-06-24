# Aauth | Laravel Package

Hierarchical Rol-Permission Based Auth Laravel Package with Limitless Hierarchical Level of Organizations

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aurorawebsoftware/aauth.svg?style=flat-square)](https://packagist.org/packages/aurora/aauth)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/aurorawebsoftware/aauth/run-tests?label=tests)](https://github.com/aurora/aauth/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/aurorawebsoftware/aauth/Check%20&%20fix%20styling?label=code%20style)](https://github.com/aurora/aauth/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/aurorawebsoftware/aauth.svg?style=flat-square)](https://packagist.org/packages/aurora/aauth)


## Support us

[<img src="https://banners.beyondco.de/AAuth%20for%20Laravel.png?theme=light&packageManager=composer+require&packageName=aurorawebsoftware%2Faauth&pattern=jigsaw&style=style_1&description=Hierarchical+Rol-Permission+Based+Laravel+Auth+Package+with+Limitless+Hierarchical+Level+of+Organizations&md=1&showWatermark=0&fontSize=175px&images=shield-check&widths=auto" />](https://github.com/AuroraWebSoftware/AAuth)



## Installation

You can install the package via composer:

```bash
composer require aurorawebsoftware/aauth
```

You can publish and run the migrations with:

```bash
php artisan migrate
```

You can publish the config file and sample data seeder with:

```bash
php artisan vendor:publish --tag="aauth-config"
php artisan vendor:publish --tag="aauth-seeders"
php artisan db:seed --class=SampleDataSeeder
```

You can publish the sample data seeder with:

```bash
php artisan vendor:publish --tag="aauth-seeders"
php artisan db:seed --class=SampleDataSeeder
```

Optionally, You can seed the sample data with:

```bash
php artisan db:seed --class=SampleDataSeeder
```


This is the example contents of the published config file:

```php
return [
    'permissions' => [
            'system' => [
                'edit_something_for_system' => 'aauth/system.edit_something_for_system',
                'create_something_for_system' => 'aauth/system.create_something_for_system',
            ],
            'organization' => [
                'edit_something_for_organization' => 'aauth/organization.edit_something_for_organization',
                'create_something_for_organization' => 'aauth/organization.create_something_for_organization',
            ],
        ],
];
```

## Main Philosophy

In computer system security, there are several approachs to restrict system access to authorized users.

Most used and known *access control method* is Rol Based Access Control (RoBAC).

In most circumstances, it's sufficient for software projects.
Basically; Roles and Permissions are assigned to the Users, The data can be accessed horizontally as single level

What if your data access needs are further more than one level? 
and what if you need to restrict and filter the data in organizational and hierarchical manner?

Let's assume we need to implement a multi-zone, multi-level school system and be our structre like this.

- Türkiye
  - A High School
    - Class 1A
    - Class 2A
  - B High School
    - Class 1A
- Germany
  - X High School
    - Class 1B
    - Class 2B

How can you restrict A High School's data from X High School Principal and Teachers?

How can you give permissions to a Class Teacher to see their students **only** ?

What if we need another level of organization in the future like this? 
and want to give access to see students data under their responsibility only for Europe Zone Principal, Türkiye Principal dynamically *without writing one line of code?*

- Europe
  - Türkiye
     - A High School
         - Class 1A
         - Class 2A
     - B High School
         - Class 1A
  - Germany
      - X High School
          - Class 1B
          - Class 2B
- America
  - USA
    - ....
    - ....
  - Canada
    - .....

**AAuth may be your first class assistant package.**

---
> If you don't need organizational roles, **AAuth** may not be suitable for your work. 
---

## AAuth Terminology
 ....
to be continued

## Usage

```php
$aAuth = new Aurora\AAuth();
echo $aAuth->echoPhrase('Hello, Aurora!');
```

## Static Analyse and Unit Test

```bash
composer analyse
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Aurora Web Software Team](https://github.com/AuroraWebSoftware)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
