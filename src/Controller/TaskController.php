<?php

namespace Drupal\concrete_finishers\Controller;

use Drupal\concrete_finishers\Plugin\Event\ServiceFinishedEvent;
use Drupal\concrete_finishers\Plugin\Event\ServiceScheduledEvent;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\node\NodeForm;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request as Sr;

/**
 * Controller routines for tablesort example routes.
 */
class TaskController extends ControllerBase
{

    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $request;

    /**
     * The Database Connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('database'),
            $container->get('request_stack')
        );
    }

    /**
     * TableSortExampleController constructor.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   The database connection.
     */
    public function __construct(Connection $database, RequestStack $request)
    {
        $this->database = $database;
        $this->request = $request;
    }

    /**
     * @param $entity_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function hideTask($entity_id)
    {
        try {
            /**
             * @var $task \Drupal\node\Entity\Node
             */
            $task = \Drupal::entityTypeManager()
                ->getStorage('node')->load($entity_id);
            $task->setPublished(false);
            $task->save();
        } catch (\Exception $e) {
            \Drupal::logger('task')->error($e->getMessage());
            return new RedirectResponse(\Drupal::destination()->get());
        }

        return new RedirectResponse(Url::fromUri('internal:/tasks')->toString());
    }

    /**
     * @param $entity_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function completeTask($entity_id)
    {
        try {
            /**
             * @var $task \Drupal\node\Entity\Node
             */
            $dt = new \DateTime('now');
            $task = \Drupal::entityTypeManager()
                ->getStorage('node')->load($entity_id);
            $task->get('field_date_completed')
                ->setValue($dt->format("Y-m-d\Th:i:s"));
            $task->setPublished(false);
            $task->save();
        } catch (\Exception $e) {
            \Drupal::logger('task')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        return new RedirectResponse(Url::fromUri('internal:/tasks')->toString());
    }

    /**
     * @param $entity_id
     *
     * @return array
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function getStartDateForm($entity_id)
    {
        $node = \Drupal::entityTypeManager()
            ->getStorage('node')->load($entity_id);
        if ($this->request->getCurrentRequest()
                ->getMethod() == Sr::METHOD_POST) {
            $event = new ServiceScheduledEvent($node, ['client' => $node]);
            $event_dispatcher = \Drupal::service('event_dispatcher');
            $event_dispatcher->dispatch(ServiceScheduledEvent::EVENT_NAME,
                $event);
        }

        $form = \Drupal::entityTypeManager()
            ->getFormObject('node', 'client_start_date_form')
            ->setEntity($node);

        return $form = \Drupal::formBuilder()->getForm($form);

       $response = new AjaxResponse();
        $response->addCommand(new OpenModalDialogCommand('Set Start Date', $form, []));

        return $response;*/

        $post = $this->request->getCurrentRequest()->get('serviceDate');
        try {
            /**
             * @var $task \Drupal\node\Entity\Node
             *
             */
            $task = \Drupal::entityTypeManager()
                ->getStorage('node')->load($entity_id);
            $task->get('field_proposed_service_date')
                ->setValue($post);
            $task->save();
        } catch (\Exception $e) {
            \Drupal::logger('task')->error($e->getMessage());
            return new JsonResponse($e);
        }

        return new JsonResponse($task);
    }

    /**
     * @param $entity_id
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function getCompletedDateForm($entity_id)
    {
        $node = \Drupal::entityTypeManager()
            ->getStorage('node')->load($entity_id);
        if ($this->request->getCurrentRequest()
                ->getMethod() == Sr::METHOD_POST) {
            $event = new ServiceFinishedEvent($node, ['client' => $node]);
            $event_dispatcher = \Drupal::service('event_dispatcher');
            $event_dispatcher->dispatch(ServiceFinishedEvent::EVENT_NAME,
                $event);

            $url = Url::fromRoute('concrete_finishers.createInvoice', [
                'entity_id' => $node->get('field_proposal_reference')
                    ->getValue(0)[0]['target_id'],
            ]);

            drupal_set_message('Create an invoice for the completed work');

            return new RedirectResponse($url->toString());
        }

        $form = \Drupal::entityTypeManager()
            ->getFormObject('node', 'client_completed_date_form')
            ->setEntity($node);

        return $form = \Drupal::formBuilder()->getForm($form);

        $response = new AjaxResponse();
        $response->addCommand(new OpenModalDialogCommand('Set Start Date', $form, []));

        return $response;*/

        $post = $this->request->getCurrentRequest()->get('serviceDate');
        try {
            /**
             * @var $task \Drupal\node\Entity\Node
             *
             */
            $task = \Drupal::entityTypeManager()
                ->getStorage('node')->load($entity_id);
            $task->get('field_proposed_service_date')
                ->setValue($post);
            $task->save();
        } catch (\Exception $e) {
            \Drupal::logger('task')->error($e->getMessage());
            return new JsonResponse($e);
        }

        return new JsonResponse($task);
    }


    /**
     * @param $entity_id
     */
    public function sendClosureEmail($entity_id)
    {

    }

}