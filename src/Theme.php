<?php

namespace Fcl\Theme;

use Closure;
use Fcl\Theme\Contracts\Theme as ThemeContract;
use Fcl\Theme\Manifest;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Factory;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Cookie;

class Theme implements ThemeContract
{
    /**
     * Theme namespace.
     */
    public static $namespace = 'theme';

    /**
     * Repository config.
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * Manifest.
     *
     * @var \Fcl\Theme\Manifest
     */
    protected $manifest;

    /**
     * Event dispatcher.
     *
     * @var \Illuminate\Events\Dispatcher
     */
    protected $dispatchesEvents;

    /**
     * Theme configuration.
     *
     * @var mixed
     */
    protected $themeConfig;

    /**
     * View.
     *
     * @var \Illuminate\View\Factory
     */
    protected $view;

    /**
     * Asset.
     *
     * @var \Fcl\Assets
     */
    protected $asset;

    /**
     * Filesystem.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Breadcrumb.
     *
     * @var \Fcl\Breadcrumb
     */
    protected $breadcrumb;

    /**
     * The name of theme.
     *
     * @var string
     */
    protected $theme;

    /**
     * The name of layout.
     *
     * @var string
     */
    protected $layout;

    /**
     * Content dot path.
     *
     * @var string
     */
    protected $content;

    /**
     * Path of all themes.
     *
     * @var array
     */
    protected $themesPath;

    /**
     * Regions in the theme.
     *
     * @var array
     */
    protected $regions = [];

    /**
     * Content arguments.
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * Injected arguments.
     *
     * @var array
     */
    protected $argInjected = [];

    /**
     * Data bindings.
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * Cookie var.
     *
     * @var Cookie
     */
    protected $cookie;

    /**
     * Engine compiler.
     *
     * @var array
     */
    protected $compilers = [];

    /**
     * Create a new theme instance.
     *
     * @param  \Illuminate\Config\Repository $config
     * @param  \Illuminate\Events\Dispatcher $events
     * @param  \Illuminate\View\Factory $view |
     * @param  \Fcl\Theme\asset $asset
     * @param  \Illuminate\Filesystem\Filesystem $files
     * @param  \Fcl\Breadcrumb|\Fcl\Theme\Breadcrumb $breadcrumb
     * @param  \Fcl\Theme\Manifest $manifest
     *
     * @return \Fcl\Theme\Theme
     */
    public function __construct(Repository $config,
        Dispatcher $events,
        Factory $view,
        Asset $asset,
        Filesystem $files,
        Breadcrumb $breadcrumb,
        Manifest $manifest) {

        $this->config = $config;

        $this->manifest = $manifest;

        $this->dispatchesEvents = $events;

        $this->view = $view;

        $this->asset = $asset;

        $this->files = $files;

        $this->breadcrumb = $breadcrumb;

        //$this->theme = $this->getConfig('themeDefault');
        $this->theme($this->getConfig('themeDefault'));

        //$this->layout = $this->getConfig('layoutDefault');
        $this->layout($this->getConfig('layoutDefault'));

        $this->compilers['blade'] = new BladeCompiler($files, 'theme');

        $this->themesPath = base_path($this->path(""));

    }

    /**
     * Compile the inject statements into valid PHP.
     *
     * @param  string  $variable
     * @param  string  $service
     * @return string
     */
    public function inject($variable, $service)
    {
        $this->argInjected[$variable] = app($service);
        /*
    var_dump("<?php \${$variable} = app('{$service}'); ?>");
     */
    }

    /**
     * Get a view
     *
     * @return View
     */
    public function view($view, $args = [])
    {
        if (is_array($view)) {
            if (isset($view['theme'])) {
                $this->theme($view['theme']);
            }

            if (isset($view['layout'])) {
                $this->layout($view['layout']);
            }

            if (isset($view['cookie'])) {
                $this->withCookie($view['cookie']);
            }

            $statusCode = (isset($view['statusCode'])) ? $view['statusCode'] : 200;

            if (empty($args)) {
                if (isset($view['args'])) {
                    $args = $view['args'];
                }
            }

            $view = $view['view'];
        }

        $this->uses($this->theme)->layout($this->layout);

        return $this->watch($view, $args)->render();
    }

    /**
     * Get or set data on manifest.
     *
     * @return Collection
     */
    public function info($property = null, $value = null)
    {
        $info = $this->manifest;

        $info->setThemePath($this->getThemePath());

        if ($value && $property) {
            $info->setProperty($property, $value);
            return $value;
        } else {
            if ($property) {
                return $info->getProperty($property);
            }
            return $info->getJsonContents();
        }
    }

    /**
     * Get all themes.
     *
     * @return Collection
     */
    public function all()
    {
        $themes = [];

        if ($this->files->exists($path)) {
            $scannedThemes = $this->files->directories($path);
            foreach ($scannedThemes as $theme) {
                $themes[] = basename($theme);
            }
        }
        return new Collection($themes);
    }

    /**
     * Get current theme name.
     *
     * @return string
     */
    public function getThemeName()
    {
        return $this->theme;
    }

    /**
     * Get current layout name.
     *
     * @return string
     */
    public function getLayoutName()
    {
        return $this->layout;
    }

    /**
     * Get theme namespace.
     *
     * @param string $path
     *
     * @return string
     */
    public function getThemeNamespace($path = '')
    {
        // Namespace relate with the theme name.
        $namespace = static::$namespace . '.' . $this->getThemeName();

        if ($path != false) {
            return $namespace . '::' . $path;
        }

        return $namespace;
    }

    /**
     * Check theme exists.
     *
     * @param  string  $theme
     * @return boolean
     */
    public function exists($theme)
    {
        $path = base_path($this->path($theme)) . '/';

        return is_dir($path);
    }

    /**
     * Link to another view.
     *
     * <code>
     *      // Look up view from another view in the same place.
     *      Theme::symlink('another')
     * </code>
     *
     * @param  string $theme
     * @return string
     */
    public function symlink($theme)
    {
        $trace = debug_backtrace();

        if (!isset($trace[1])) {
            return;
        }

        $link = str_replace($this->getThemeName(), $theme, Arr::get($trace[1], 'file'));

        extract($this->arguments);
        extract($this->view->getShared());

        return require $link;
    }

    /**
     * Symlink with inherit.
     *
     * This method is the same symlink, but try to find inherit,
     * from config.
     *
     * @param  string $theme
     * @return string
     */
    public function symlinkWithFindInherit($theme)
    {
        $trace = debug_backtrace();

        if (!isset($trace[1])) {
            return;
        }

        // change backslash to forward slash (for windows file system)
        $path = str_replace("\\", "/", Arr::get($trace[1], 'file'));

        $config = $this->getConfig();

        $link = preg_replace("#({$config['themeDir']}/)[^/]+#", "$1{$theme}", $path);

        extract($this->arguments);
        extract($this->view->getShared());

        return require $link;
    }

    /**
     * Get theme config.
     *
     * @param  string $key
     * @return mixed
     */
    public function getConfig($key = null)
    {
        // Main package config.
        if (!$this->themeConfig) {
            $this->themeConfig = $this->config->get('theme');
        }

        // Config inside a public theme.
        // This config having buffer by array object.
        if ($this->theme and !isset($this->themeConfig['themes'][$this->theme])) {
            $this->themeConfig['themes'][$this->theme] = [];

            try {
                // Require public theme config.
                $minorConfigPath = base_path($this->themeConfig['themeDir'] . '/' . $this->theme . '/config.php');

                $this->themeConfig['themes'][$this->theme] = $this->files->getRequire($minorConfigPath);
            } catch (\Illuminate\Filesystem\FileNotFoundException $e) {
                //var_dump($e->getMessage());
            }
        }

        // Evaluate theme config.
        $this->themeConfig = $this->evaluateConfig($this->themeConfig);

        return is_null($key) ? $this->themeConfig : Arr::get($this->themeConfig, $key);
    }

    /**
     * Evaluate config.
     *
     * Config minor is at public folder [theme]/config.php,
     * thet can be override package config.
     *
     * @param  mixed $config
     * @return mixed
     */
    protected function evaluateConfig($config)
    {
        if (!isset($config['themes'][$this->theme])) {
            return $config;
        }

        // Config inside a public theme.
        $minorConfig = $config['themes'][$this->theme];

        // Before event is special case, It's combination.
        if (isset($minorConfig['events']['before'])) {
            $minorConfig['events']['appendBefore'] = $minorConfig['events']['before'];
            unset($minorConfig['events']['before']);
        }

        // Merge two config into one.
        $config = array_replace_recursive($config, $minorConfig);

        // Reset theme config.
        $config['themes'][$this->theme] = [];

        return $config;
    }

    /**
     * Add location path to look up.
     *
     * @param string $location
     */
    protected function addPathLocation($location)
    {
        // First path is in the selected theme.
        $hints[] = base_path($location);

        // This is nice feature to use inherit from another.
        if ($this->getConfig('inherit')) {
            // Inherit from theme name.
            $inherit = $this->getConfig('inherit');

            // Inherit theme path.
            $inheritPath = base_path($this->path($inherit));

            if ($this->files->isDirectory($inheritPath)) {
                array_push($hints, $inheritPath);
            }
        }

        // Add namespace with hinting paths.
        $this->view->addNamespace($this->getThemeNamespace(), $hints);
    }

    /**
     * Fire event to config listener.
     *
     * @param  string $event
     * @param  mixed  $args
     * @return void
     */
    public function fire($event, $args)
    {
        $onEvent = $this->getConfig('events.' . $event);

        if ($onEvent instanceof Closure) {
            $onEvent($args);
        }
    }

    /**
     * Set up a theme name.
     *
     * @param  string $theme
     * @throws UnknownThemeException
     * @return Theme
     */
    public function theme($theme = null)
    {
        // If theme name is not set, so use default from config.
        if ($theme != false) {
            $this->theme = $theme;
        }

        // Is theme ready?
        if (!$this->exists($theme)) {
            throw new UnknownThemeException("Theme [$theme] not found.");
        }

        // Add location to look up view.
        $this->addPathLocation($this->path());

        // Fire event before set up a theme.
        $this->fire('before', $this);

        // Before from a public theme config.
        $this->fire('appendBefore', $this);

        // Add asset path to asset container.
        $this->asset->addPath($this->path() . '/assets');

        return $this;
    }

    /**
     * Alias of theme method.
     *
     * @param  string $theme
     * @return Theme
     */
    public function uses($theme = null)
    {
        return $this->theme($theme);
    }

    /**
     * Set up a layout name.
     *
     * @param  string $layout
     * @return Theme
     */
    public function layout($layout)
    {
        // If layout name is not set, so use default from config.
        if ($layout != false) {
            $this->layout = $layout;
        }

        return $this;
    }

    /**
     * Get theme path.
     *
     * @param  string $forceThemeName
     * @return string
     */
    public function path($forceThemeName = null)
    {
        $themeDir = $this->getConfig('themeDir');

        $theme = $this->theme;

        if ($forceThemeName != false) {
            $theme = $forceThemeName;
        }

        return $themeDir . '/' . $theme;
    }

    /**
     * Get theme path.
     *
     * @return Collection
     */
    public function getThemePath()
    {
        return base_path($this->path($this->theme)) . '/';
    }

    /**
     * Set a place to regions.
     *
     * @param  string $region
     * @param  string $value
     * @return Theme
     */
    public function set($region, $value)
    {
        // Content is reserve region for render sub-view.
        if ($region == 'content') {
            return;
        }

        $this->regions[$region] = $value;

        return $this;
    }

    /**
     * Append a place to existing region.
     *
     * @param  string $region
     * @param  string $value
     * @return Theme
     */
    public function append($region, $value)
    {
        return $this->appendOrPrepend($region, $value, 'append');
    }

    /**
     * Prepend a place to existing region.
     *
     * @param  string $region
     * @param  string $value
     * @return Theme
     */
    public function prepend($region, $value)
    {
        return $this->appendOrPrepend($region, $value, 'prepend');
    }

    /**
     * Append or prepend existing region.
     *
     * @param  string $region
     * @param  string $value
     * @param  string $type
     * @return Theme
     */
    protected function appendOrPrepend($region, $value, $type = 'append')
    {
        // If region not found, create a new region.
        if (isset($this->regions[$region])) {
            switch ($type) {
                case 'prepend':
                    $this->regions[$region] = $value . $this->regions[$region];
                    break;
                case 'append':
                    $this->regions[$region] .= $value;
                    break;
            }
        } else {
            $this->set($region, $value);
        }

        return $this;
    }

    /**
     * Binding data to view.
     *
     * @param  string $variable
     * @param  mixed  $callback
     * @return mixed
     */
    public function bind($variable, $callback = null)
    {
        $name = 'bind.' . $variable;

        // If callback pass, so put in a queue.
        if (!empty($callback)) {
            // Preparing callback in to queues.
            $this->dispatchesEvents->listen($name, function () use ($callback, $variable) {
                return ($callback instanceof Closure) ? $callback() : $callback;
            });
        }

        // Passing variable to closure.
        $_events = &$this->dispatchesEvents;
        $_bindings = &$this->bindings;

        // Buffer processes to save request.
        return Arr::get($this->bindings, $name, function () use (&$_events, &$_bindings, $name) {
            $response = current($_events->fire($name));
            Arr::set($_bindings, $name, $response);
            return $response;
        });
    }

    /**
     * Check having binded data.
     *
     * @param  string $variable
     * @return boolean
     */
    public function binded($variable)
    {
        $name = 'bind.' . $variable;

        return $this->dispatchesEvents->hasListeners($name);
    }

    /**
     * Assign data across all views.
     *
     * @param  mixed $key
     * @param  mixed $value
     * @return mixed
     */
    public function share($key, $value)
    {
        return $this->view->share($key, $value);
    }

    /**
     * Set up a partial.
     *
     * @param  string $view
     * @param  array $args
     * @throws UnknownPartialFileException
     * @return mixed
     */
    public function partial($view, $args = [])
    {
        $partialDir = $this->getThemeNamespace('partials');

        return $this->loadPartial($view, $partialDir, $args);
    }

    /**
     * The same as "partial", but having prefix layout.
     *
     * @param  string $view
     * @param  array $args
     * @throws UnknownPartialFileException
     * @return mixed
     */
    public function partialWithLayout($view, $args = [])
    {
        $view = $this->getLayoutName() . '.' . $view;

        return $this->partial($view, $args);
    }

    /**
     * Load a partial
     *
     * @param  string $view
     * @param  string $partialDir
     * @param  array  $args
     * @throws UnknownPartialFileException
     * @return mixed
     */
    public function loadPartial($view, $partialDir, $args)
    {
        $path = $partialDir . '.' . $view;

        if (!$this->view->exists($path)) {
            throw new UnknownPartialFileException("Partial view [$view] not found.");
        }

        $partial = $this->view->make($path, $args)->render();
        $this->regions[$view] = $partial;

        return $this->regions[$view];
    }

    /**
     * Watch and set up a partial from anywhere.
     *
     * This method will first try to load the partial from current theme. If partial
     * is not found in theme then it loads it from app (i.e. app/views/partials)
     *
     * @param  string $view
     * @param  array $args
     * @throws UnknownPartialFileException
     * @return mixed
     */
    public function watchPartial($view, $args = [])
    {
        try {
            return $this->partial($view, $args);
        } catch (UnknownPartialFileException $e) {
            return $this->loadPartial($view, 'partials', $args);
        }
    }

    /**
     * Widget instance.
     *
     * @param  string $className
     * @param  array $attributes
     * @throws UnknownWidgetClassException
     * @return Fcl\Theme\Widget
     */
    public function widget($className, $attributes = [])
    {
        static $widgets = [];

        // If the class name is not lead with upper case add prefix "Widget".
        if (!preg_match('|^[A-Z]|', $className)) {
            $className = ucfirst($className);
        }

        $widgetNamespace = $this->getConfig('namespaces.widget');

        $className = $widgetNamespace . '\\' . $className;

        if (!$instance = Arr::get($widgets, $className)) {
            $reflector = new ReflectionClass($className);

            if (!$reflector->isInstantiable()) {
                throw new UnknownWidgetClassException("Widget target [$className] is not instantiable.");
            }

            $instance = $reflector->newInstance($this, $this->config, $this->view);
            Arr::set($widgets, $className, $instance);
        }

        $instance->setAttributes($attributes);
        $instance->beginWidget();
        $instance->endWidget();

        return $instance;
    }

    /**
     * Hook a partial before rendering.
     *
     * @param  mixed   $view
     * @param  closure $callback
     * @return void
     */
    public function partialComposer($view, $callback, $layout = null)
    {
        if (!is_array($view)) {
            $view = [$view];
        }

        // Partial path with namespace.
        $path = $this->getThemeNamespace('partials');

        // This code support partialWithLayout.
        if (!is_null($layout)) {
            $path = $path . '.' . $layout;
        }

        $view = array_map(function ($v) use ($path) {
            return $path . '.' . $v;
        }, $view);

        $this->view->composer($view, $callback);
    }

    /**
     * Get compiler.
     *
     * @param  string $compiler
     * @return object
     */
    public function getCompiler($compiler)
    {
        if (isset($this->compilers[$compiler])) {
            return $this->compilers[$compiler];
        }
    }

    /**
     * Parses and compiles strings by using blade template system.
     *
     * @param  string $str
     * @param  array $data
     * @param  boolean $phpCompile
     * @throws \Exception
     * @return string
     */
    public function blader($str, $data = [], $phpCompile = true)
    {
        if ($phpCompile == false) {
            $patterns = ['|<\?|', '|<\?php|', '|<\%|', '|\?>|', '|\%>|'];
            $replacements = ['&lt;?', '&lt;php', '&lt;%', '?&gt;', '%&gt;'];

            $str = preg_replace($patterns, $replacements, $str);
        }

        // Get blade compiler.
        $parsed = $this->getCompiler('blade')->compileString($str);

        ob_start() and extract($data, EXTR_SKIP);

        try {
            eval('?>' . $parsed);
        } catch (\Exception $e) {
            ob_end_clean();throw $e;
        }

        $str = ob_get_contents();
        ob_end_clean();

        return $str;
    }

    /**
     * Compile blade without PHP.
     *
     * @param  string $str
     * @param  array  $data
     * @return string
     */
    public function bladerWithOutServerScript($str, $data = [])
    {
        return $this->blader($str, $data, false);
    }

    /**
     * Check region exists.
     *
     * @param  string  $region
     * @return boolean
     */
    public function has($region)
    {
        return (boolean) isset($this->regions[$region]);
    }

    /**
     * Render a region.
     *
     * @param  string $region
     * @param  mixed  $default
     * @return string
     */
    public function get($region, $default = null)
    {
        if ($this->has($region)) {
            return $this->regions[$region];
        }

        return $default ? $default : '';
    }

    /**
     * Place content in sub-view.
     *
     * @return string
     */
    public function content()
    {
        return $this->regions['content'];
    }

    /**
     * Return asset instance.
     *
     * @return \Fcl\Theme\Asset
     */
    public function asset()
    {
        return $this->asset;
    }

    /**
     * Return breadcrumb instance.
     *
     * @return \Fcl\Theme\Breadcrumb
     */
    public function breadcrumb()
    {
        return $this->breadcrumb;
    }

    /**
     * Set up a content to template.
     *
     * @param  string $view
     * @param  array  $args
     * @param  string $type
     * @return Theme
     */
    public function of($view, $args = [], $type = null)
    {
        // Layout.
        $layout = ucfirst($this->layout);

        // Fire event global assets.
        $this->fire('asset', $this->asset);

        // Fire event before render theme.
        $this->fire('beforeRenderTheme', $this);

        // Fire event before render layout.
        $this->fire('beforeRenderLayout.' . $this->layout, $this);

        // Keeping arguments.
        $this->arguments = $args;

        // Compile string blade, or from file path.
        switch ($type) {
            case 'blade':
                $content = $this->bladerWithOutServerScript($view, $args);
                break;
            default:
                $content = $this->view->make($view, $args)->render();
                break;
        }

        // View path of content.
        $this->content = $view;

        // Set up a content regional.
        $this->regions['content'] = $content;

        return $this;
    }

    /**
     * The same as "of", but having prefix layout.
     *
     * @param  string $view
     * @param  array  $args
     * @param  string $type
     * @return Theme
     */
    public function ofWithLayout($view, $args = [], $type = null)
    {
        $view = $this->getLayoutName() . '.' . $view;

        return $this->of($view, $args, $type);
    }

    /**
     * Container view.
     *
     * Using a container module view inside a theme, this is
     * useful when you separate a view inside a theme.
     *
     * @param  string $view
     * @param  array  $args
     * @param  string $type
     * @return Theme
     */
    public function scope($view, $args = [], $type = null)
    {
        // Add namespace to find in a theme path.
        $path = $this->getThemeNamespace('views.' . $view);

        return $this->of($path, $args, $type);
    }

    /**
     * The same as "scope", but having prefix layout.
     *
     * @param  string $view
     * @param  array  $args
     * @param  string $type
     * @return Theme
     */
    public function scopeWithLayout($view, $args = [], $type = null)
    {
        $view = $this->getLayoutName() . '.' . $view;

        return $this->scope($view, $args, $type);
    }

    /**
     * Load subview from direct path.
     *
     * @param  string $view
     * @param  array  $args
     * @return Theme
     */
    public function load($view, $args = [])
    {
        $view = ltrim($view, '/');

        $segments = explode('/', str_replace('.', '/', $view));

        // Pop file from segments.
        $view = array_pop($segments);

        // Custom directory path.
        $pathOfView = app('path.base') . '/' . implode('/', $segments);

        // Add temporary path with a hint type.
        $this->view->addNamespace('custom', $pathOfView);

        return $this->of('custom::' . $view, $args);
    }

    /**
     * Watch view file in anywhere.
     *
     * Finding from scope first, then try to find from application view.
     *
     * @param  string $view
     * @param  array  $args
     * @param  string $type
     * @return Theme
     */
    public function watch($view, $args = [], $type = null)
    {
        try {
            return $this->scope($view, $args, $type);
        } catch (\InvalidArgumentException $e) {
            return $this->of($view, $args, $type);
        }
    }

    /**
     * The same as "watch", but having prefix layout.
     *
     * Finding from scope first, then try to find from application view.
     *
     * @param  string $view
     * @param  array  $args
     * @param  string $type
     * @return Theme
     */
    public function watchWithLayout($view, $args = [], $type = null)
    {
        try {
            return $this->scopeWithLayout($view, $args, $type);
        } catch (\InvalidArgumentException $e) {
            return $this->ofWithLayout($view, $args, $type);
        }
    }

    /**
     * Get all arguments assigned to content.
     *
     * @return mixed
     */
    public function getContentArguments()
    {
        return $this->arguments;
    }

    /**
     * Get a argument assigned to content.
     *
     * @param  string $key
     * @param null $default
     * @return mixed
     */
    public function getContentArgument($key, $default = null)
    {
        return Arr::get($this->arguments, $key, $default);
    }

    /**
     * Checking content argument existing.
     *
     * @param  string  $key
     * @return boolean
     */
    public function hasContentArgument($key)
    {
        return (bool) isset($this->arguments[$key]);
    }

    /**
     * Find view location.
     *
     * @param  boolean $realpath
     * @return string
     */
    public function location($realpath = false)
    {
        try {
            if ($this->view->exists($this->content)) {
                return ($realpath) ? $this->view->getFinder()->find($this->content) : $this->content;
            }
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * It's similar to location, but will look up from both
     * application's view and theme's view.
     *
     * ex. Theme::which('general.welcome');
     *
     * @param  string  $view
     * @param  boolean $realpath
     * @return string
     */
    public function which($view, $realpath = false)
    {
        return $this->watch($view)->location($realpath);
    }

    /**
     * Compile from string.
     *
     * @param  string $str
     * @param  array  $args
     * @param  string $type
     * @return Theme
     */
    public function string($str, $args = [], $type = 'blade')
    {
        $shared = $this->view->getShared();
        $data['errors'] = $shared['errors'];
        $args = array_merge($data, $args);

        return $this->of($str, $args, $type);
    }

    /**
     * Set cookie to response.
     *
     * @param  Cookie $cookie
     * @return Theme
     */
    public function withCookie(Cookie $cookie)
    {
        $this->cookie = $cookie;

        return $this;
    }

    /**
     * Return a template with content.
     *
     * @param  integer $statusCode
     * @throws UnknownLayoutFileException
     * @return Response
     */
    public function render($statusCode = 200)
    {
        // Fire the event before render.
        $this->fire('after', $this);

        // Flush asset that need to serve.
        $this->asset->flush();

        $path = $this->getThemeNamespace('layouts.' . $this->layout);

        if (!$this->view->exists($path)) {
            throw new UnknownLayoutFileException("Layout [$this->layout] not found.");
        }

        $content = $this->view->make($path)->render();

        // Append status code to view.
        $content = new Response($content, $statusCode);

        // Having cookie set.
        if ($this->cookie) {
            $content->withCookie($this->cookie);
        }

        return $content;
    }

    /**
     * Magic method for set, prepend, append, has, get.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters = [])
    {
        $callable = preg_split('|[A-Z]|', $method);

        if (in_array($callable[0], ['set', 'prepend', 'append', 'has', 'get'])) {
            $value = lcfirst(preg_replace('|^' . $callable[0] . '|', '', $method));
            array_unshift($parameters, $value);

            return call_user_func_array([$this, $callable[0]], $parameters);
        }

        trigger_error('Call to undefined method ' . __CLASS__ . '::' . $method . '()', E_USER_ERROR);
    }
}
