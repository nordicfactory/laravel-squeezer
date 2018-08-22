<?php namespace Ardentic\Squeezer;

namespace Ardentic\Squeezer;

use Illuminate\View\Engines\PhpEngine;
use Illuminate\Support\ServiceProvider;
use Ardentic\Squeezer\Engines\ModifiedCompilerEngine;
use Ardentic\Squeezer\Compilers\ModifiedBladeCompiler;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Compilers\BladeCompiler;


class SqueezerServiceProvider extends ServiceProvider {

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $app = parent::boot();

        $resolver = $this->app['view.engine.resolver'];
        $app = $this->app;

        $app->singleton('blade.compiler', function ($app) {
            $cache = $app['config']['view.compiled'];

            return new ModifiedBladeCompiler($app['files'], $cache);
        });

        $resolver->register('blade', function () use ($app) {
            return new ModifiedCompilerEngine($app['blade.compiler']);
        });

        \App::bind('squeezer', function()
        {
            return new \Ardentic\Squeezer\Squeezer;
        });
        Squeezer::attach($this->app);

    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        //
    }

}

