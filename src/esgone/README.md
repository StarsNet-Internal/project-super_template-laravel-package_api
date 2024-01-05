# Package Installation Guide

1. Create these directories on the root of the Laravel project, if they don't exist

```
packages/
├─ *vendor_name/
```

For example, for our case:

```
packages/
├─ starsnet/
```

You can also create directories simply with a single command via terminal:

```
mkdir -p packages/<vendor_name>

Reference:
mkdir -p packages/starsnet
```

2. Git clone this package under `packages/<vendor_name>`

3. On the root of Laravel main project composer.json, edit autoload.psr-4 as follows:

```
"autoload": {
   "psr-4": {
      "App\\": "app/"
		/*
       * Custom packages inserts here
       * key: namespace prefix
       * value: location up to the src directory, where the ServiceProvider class is located
       */
	  "Starsnet\\Project\\": "packages/starsnet/project/src/"
   },
},
```

4. Edit `config/app.php` on Laravel main project, as follows:

```
'providers' => [
        /*
         * Application Service Providers...
         */
         ...
        App\Providers\RouteServiceProvider::class, // after this line
        // Custom packages
        // Refer the className from updated autoload.psr-4 in composer.json
        // Register package's ServiceProvider class here
        Starsnet\Project\ProjectServiceProvider::class
 ],
```

5. Run `composer dump-autoload`, to force the project auto-register newly-included files/path

# Reference links

[How to create Laravel custom package](https://www.notion.so/starsnet/Creating-Custom-Plugin-Package-4b2de2a4d69e42e9947563536cb27f77)

[How to install Laravel custom package](https://www.notion.so/starsnet/Creating-Custom-Plugin-Package-4b2de2a4d69e42e9947563536cb27f77)
