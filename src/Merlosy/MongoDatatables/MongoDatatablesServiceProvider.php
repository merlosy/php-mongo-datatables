<?php namespace Merlosy\MongoDatatables;

use Illuminate\Support\ServiceProvider;

class MongoDatatablesServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('merlosy/mongo-datatables');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['mongo-datatables'] = $this->app->share(function($app)
	    {
	        return new MongoDatatables;
	    });
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('mongo-datatables');
	}

}
