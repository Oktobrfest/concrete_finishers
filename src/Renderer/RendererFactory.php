<?php

namespace Drupal\concrete_finishers\Renderer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_print\PrintEngineException;
use Drupal\entity_print\Renderer\RendererFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * The RendererFactory class.
 */
class RendererFactory implements RendererFactoryInterface {

  use ContainerAwareTrait;

  /**
   * {@inheritdoc}
   */
  public function create($item, $context = 'entity') {
    // If we get an array or something, just look at the first one.
    if (is_array($item)) {
      $item = array_pop($item);
    }

    if ($item instanceof EntityInterface) {
      // Support specific renderers for each entity type.
      $id = $item->getEntityType()->id();
      if ($this->container->has("concrete_finishers.renderer.$id")) {
        return $this->container->get("concrete_finishers.renderer.$id");
      }

      // Returns the generic service for content/config entities.
      $group = $item->getEntityType()->getGroup();
      if ($this->container->has("concrete_finishers.renderer.$group")) {
        return $this->container->get("concrete_finishers.renderer.$group");
      }
    }

    throw new PrintEngineException(sprintf('Rendering not yet supported for "%s". Entity Print context "%s"', is_object($item) ? get_class($item) : $item, $context));
  }

}
