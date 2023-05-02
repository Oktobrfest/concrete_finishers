<?php

namespace Drupal\concrete_finishers\Ajax;
use Drupal\Core\Ajax\CommandInterface;

class ScrollToElementCommand implements CommandInterface {
    protected $element;

    public function __construct($element)
    {
        $this->element = $element;
    }

    public function render()
    {
        return [
            'command' => 'scrollTo',
            'element' => $this->element,
        ];
    }
}