<?php

namespace Drupal\concrete_finishers;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\entity_print\Event\PrintEvents;
use Drupal\entity_print\Event\PreSendPrintEvent;
use Drupal\entity_print\Plugin\PrintEngineInterface;
use Drupal\concrete_finishers\PrintBuilderInterface;
use Drupal\entity_print\Renderer\RendererFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PrintBuilder implements PrintBuilderInterface
{

    use StringTranslationTrait;

    /**
     * The Print Renderer factory.
     *
     * @var \Drupal\concrete_finishers\Renderer\RendererFactoryInterface
     */
    protected $rendererFactory;

    /**
     * The event dispatcher.
     *
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var string
     */
    protected $theme;

    /**
     * Constructs a new EntityPrintPrintBuilder.
     *
     * @param \Drupal\entity_print\Renderer\RendererFactoryInterface $renderer_factory
     *   The Renderer factory.
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
     *   The event dispatcher.
     */
    public function __construct(
        RendererFactoryInterface $renderer_factory,
        EventDispatcherInterface $event_dispatcher,
        TranslationInterface $string_translation
    ) {
        $this->rendererFactory = $renderer_factory;
        $this->dispatcher = $event_dispatcher;
        $this->stringTranslation = $string_translation;
    }

    /**
     * {@inheritdoc}
     */
    public function deliverPrintable(
        array $entities,
        PrintEngineInterface $print_engine,
        $force_download = false,
        $use_default_css = true,
        $theme = 'entity_print'
    ) {
        $this->theme = $theme;
        $renderer = $this->prepareRenderer($entities, $print_engine,
            $use_default_css);

        // Allow other modules to alter the generated Print object.
        $this->dispatcher->dispatch(PrintEvents::PRE_SEND,
            new PreSendPrintEvent($print_engine, $entities));

        // Calculate the filename.
        $filename = $renderer->getFilename($entities) . '.' . $print_engine->getExportType()
                ->getFileExtension();

        return $print_engine->send($filename, $force_download);
    }

    /**
     * {@inheritdoc}
     */
    public function printHtml(
        EntityInterface $entity,
        $use_default_css = true,
        $optimize_css = true,
        $theme = 'entity_print'
    ) {
        $this->theme = $theme;
        $renderer = $this->rendererFactory->create([$entity]);
        $content[] = $renderer->render([$entity]);

        $preview = $print = false;
        $current_path = \Drupal::service('path.current')->getPath();
        if (strpos($current_path, 'preview') !== false) {
            $preview = true;
        }

        if (strpos($current_path, 'print') !== false) {
            $print = true;
        }

        $render = [
            '#theme' => $this->theme,
            '#title' => $this->t('View'),
            '#content' => $content,
            '#attached' => [],
            '#preview' => $preview,
            '#print' => $print,
            '#entity_id' => $entity->id(),
        ];
        $html = $renderer->generateHtml([$entity], $render, $use_default_css,
            false);

        return str_replace('http:', 'https:', $html);
    }

    /**
     * {@inheritdoc}
     */
    public function savePrintable(
        array $entities,
        PrintEngineInterface $print_engine,
        $scheme = 'public',
        $filename = false,
        $use_default_css = true,
        $theme = 'entity_print'
    ) {
        $this->theme = $theme;
        $renderer = $this->prepareRenderer($entities, $print_engine,
            $use_default_css);

        // Allow other modules to alter the generated Print object.
        $this->dispatcher->dispatch(PrintEvents::PRE_SEND,
            new PreSendPrintEvent($print_engine, $entities));

        // If we didn't have a URI passed in the generate one.
        if (!$filename) {
            $filename = $renderer->getFilename($entities) . '.' . $print_engine->getExportType()
                    ->getFileExtension();
        }

        $uri = "$scheme://$filename";

        // Save the file.
        return file_unmanaged_save_data($print_engine->getBlob(), $uri,
            FILE_EXISTS_REPLACE);
    }

    /**
     * Configure the print engine with the passed entities.
     *
     * @param array $entities
     *   An array of entities.
     * @param \Drupal\entity_print\Plugin\PrintEngineInterface $print_engine
     *   The print engine.
     * @param bool $use_default_css
     *   TRUE if we want the default CSS included.
     *
     * @return \Drupal\entity_print\Renderer\RendererInterface
     *   A print renderer.
     */
    protected function prepareRenderer(
        array $entities,
        PrintEngineInterface $print_engine,
        $use_default_css
    ) {
        if (empty($entities)) {
            throw new \InvalidArgumentException('You must pass at least 1 entity');
        }

        $renderer = $this->rendererFactory->create($entities);
        $content = $renderer->render($entities);

        $preview = $print = false;
        $current_path = \Drupal::service('path.current')->getPath();
        if (strpos($current_path, 'preview') !== false) {
            $preview = true;
        }

        if (strpos($current_path, 'print') !== false) {
            $print = true;
        }

        $first = reset($entities);
        $render = [
            '#theme' => $this->theme,
            '#title' => $this->t('View'),
            '#content' => $content,
            '#attached' => [],
            '#preview' => $preview,
            '#print' => $print,
            '#entity_id' => $first->id(),
        ];

        $print_engine->addPage($renderer->generateHtml($entities, $render,
            $use_default_css, false));

        return $renderer;
    }

}
