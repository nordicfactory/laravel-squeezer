# Squeezer

Squeezer is an extension to Laravel's templating engine Blade. It adds powerful features like `@embed`, `@class` and `@style`.

## Install with composer

In your favorite terminal app just run:

```bash
composer require ardentic/squeezer
```

## Add Squeezer to Laravel

In `config/app.php` add the following:

```php
'providers' => [
  Ardentic\Squeezer\SqueezerServiceProvider::class
]
```

```php
'aliases' => [
  'Squeezer' => Ardentic\Squeezer\SqueezerFacade::class
]
```

## Examples

Some basic examples of what you can achive with Squeezer.

### @style

`@style` will allow you to generate style attributes as a string from a named array.

**Example**

```php
<?php
  $styles = [
    'top' => '0',
    'left' => '0',
    'background-color' => '#ccc'
  ];
?>

<div @style($styles)></div>
```

This will generate the following result:

```php
<div style="top: 0; left: 0; background-color: #ccc;"></div>
```

### @class

`@class` will allow you to generate a class list as a string from a named array.

**Example**

```php
<?php
  $classes = [
    'button',
    'button--wide'
    'is-active' => true,
    'is-disabled' => false
  ];
?>

<div @class($classes)></div>
```

This will generate the following result:

```php
<div class="button button--wide is-active"></div>
```

### @embed

`@embed` will allow you to embed view components inside other view components much like `@extends` work in Blade. While `@section` and `@extends` can't be nested, `@embed` can.

`@embed` will also allow you to pass local variables and hook up ViewComposers to the component you are embedding.

**Basic concept of @embed**

*wrapper.blade.php*

```php
<div class="wrapper">
  @block('content')
</div>
```

*component.blade.php*

```php
@embed('wrapper')
  @block('content')
    <div class="component"></div>
  @endblock
@endembed
```

This will generate the following result:

```php
<div class="wrapper">
  <div class="component"></div>
</div>
```

**Passing local variables with @embed**

*wrapper.blade.php*

```php
<div class="wrapper" data-layout="{{ $layout }}">
  @block('content')
</div>
```

*component.blade.php*

```php
@embed('wrapper', ['layout' => 'slim'])
  @block('content')
    <div class="component"></div>
  @endblock
@endembed
```

This will generate the following result:

```php
<div class="wrapper" data-layout="slim">
  <div class="component"></div>
</div>
```

**Using @embed with a ViewComposer**

*AppServiceProvider.php*

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
  public function boot()
  {
    view()->composer('wrapper', function($view) {
      $view->with('layout', 'slim');
    });
  }

  public function register()
  {
    //
  }
}
```

*wrapper.blade.php*

```php
<div class="wrapper" data-layout="{{ $layout }}">
  @block('content')
</div>
```

*component.blade.php*

```php
@embed('wrapper')
  @block('content')
    <div class="component"></div>
  @endblock
@endembed
```

This will generate the following result:

```php
<div class="wrapper" data-layout="slim">
  <div class="component"></div>
</div>
```
