<?php

namespace {widgetNamespace};

use Fcl\Theme\Theme;
use Fcl\Theme\Widget;

class {widgetClass} extends Widget
{
    /**
     * Widget template.
     *
     * @var string
     */
    public $template = '{widgetTemplate}';

    /**
     * Watching widget tpl on everywhere.
     *
     * @var boolean
     */
    public $watch = {watch};

    /**
     * Arrtibutes pass from a widget.
     *
     * @var array
     */
    public $attributes = [
        //'userId' => 9999,
        'label'  => 'Default widget label',
    ];

    /**
     * Turn on/off widget.
     *
     * @var boolean
     */
    public $enable = true;

    /**
     * Code to start this widget.
     *
     * @return void
     */
    public function init(Theme $theme)
    {
        // Initialize widget.

        // $theme->asset()->usePath()->add('widget-name', 'js/widget-execute.js', ['jquery', 'jqueryui']);
        // $this->setAttribute('user', User::find($this->getAttribute('userId')));
    }

    /**
     * Logic given to a widget and pass to widget's view.
     *
     * @return array
     */
    public function run()
    {
        // $label = $this->getAttribute('label');

        // $this->setAttribute('label', 'changed');

        $attrs = $this->getAttributes();

        return $attrs;
    }
}
