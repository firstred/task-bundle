<?php

namespace Glooby\TaskBundle\Queue;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NoResultException;
use Glooby\TaskBundle\Entity\QueuedTask;
use Glooby\TaskBundle\Manager\TaskManager;

/**
 * @author Emil Kilhage
 */
class QueueScheduler
{
    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @param ManagerRegistry $doctrine
     */
    public function setDoctrine($doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @var TaskManager
     */
    private $taskManager;

    /**
     * @param TaskManager $taskManager
     */
    public function setTaskManager(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    /**
     *
     */
    public function schedule()
    {
        $repo = $this->doctrine->getManager()
            ->getRepository('GloobyTaskBundle:Schedule');

        $queueRepo = $this->doctrine->getManager()
            ->getRepository('GloobyTaskBundle:QueuedTask');

        foreach ($repo->findActive() as $schedule) {
            $expression = $schedule->parseExpression();
            $nextExecuteAt = $expression->getNextRunDate();
            $prevExecuteAt = $expression->getPreviousRunDate();
            $executeAt = null;

            try {
                $queueRepo->getByNameAndExecuteAtBeforeNow($schedule->getName());
            } catch (NoResultException $e) {
                $executeAt = $prevExecuteAt;
            }

            if (null === $executeAt) {
                try {
                    $queueRepo->getByNameAndExecuteAt($schedule->getName(), $nextExecuteAt);
                } catch (NoResultException $e) {
                    $executeAt = $nextExecuteAt;
                }
            }

            if (null !== $executeAt) {
                $this->taskManager->queue($schedule->getName(), $executeAt, $schedule->getParams());
            }
        }

        $this->doctrine->getManager()->flush();
    }
}