<?php

namespace Oro\Bundle\MessageQueueBundle\Tests\Functional\Job;

use Doctrine\DBAL\Connection;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Oro\Bundle\MessageQueueBundle\Tests\Functional\DataFixtures\LoadJobData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\MessageQueue\Job\DuplicateJobException;
use Oro\Component\MessageQueue\Job\UniqueJobHandler;

/**
 * @dbIsolationPerTest
 */
class UniqueJobHandlerTest extends WebTestCase
{
    /** @var UniqueJobHandler */
    private $uniqueJobHandler;

    protected function setUp(): void
    {
        $this->initClient();
        $this->uniqueJobHandler = $this->getContainer()->get('oro_message_queue.job.unique_job_handler');
        $this->loadFixtures([LoadJobData::class]);
    }

    private function getConnection(): ?Connection
    {
        return $this->getContainer()->get('doctrine')->getManager()->getConnection();
    }

    public function testShouldThrowIfDuplicateJobOwnerIdAndName()
    {
        $existingJob = $this->getReference(LoadJobData::JOB_1);

        $job = new Job();
        $job->setOwnerId($existingJob->getOwnerId());
        $job->setName($existingJob->getName());
        $job->setUnique(true);
        $job->setStatus(Job::STATUS_NEW);
        $job->setCreatedAt(new \DateTime());

        $this->expectException(DuplicateJobException::class);
        $this->uniqueJobHandler->insert($this->getConnection(), $job);
    }

    public function testShouldThrowIfDuplicateJobOwnerId()
    {
        $existingJob = $this->getReference(LoadJobData::JOB_1);

        $job = new Job();
        $job->setOwnerId($existingJob->getOwnerId());
        $job->setName($existingJob->getName().'.1');
        $job->setUnique(true);
        $job->setStatus(Job::STATUS_NEW);
        $job->setCreatedAt(new \DateTime());

        $this->expectException(DuplicateJobException::class);
        $this->uniqueJobHandler->insert($this->getConnection(), $job);
    }

    public function testShouldThrowIfDuplicateJobName()
    {
        $existingJob = $this->getReference(LoadJobData::JOB_1);

        $job = new Job();
        $job->setOwnerId($existingJob->getOwnerId().'.1');
        $job->setName($existingJob->getName());
        $job->setUnique(true);
        $job->setStatus(Job::STATUS_NEW);
        $job->setCreatedAt(new \DateTime());

        $this->expectException(DuplicateJobException::class);
        $this->uniqueJobHandler->insert($this->getConnection(), $job);
    }

    public function testShouldThrowIfDuplicateJobOwnerIdWithPreSelect()
    {
        $this->uniqueJobHandler->setPreSelectSupport(true);

        $existingJob = $this->getReference(LoadJobData::JOB_1);

        $job = new Job();
        $job->setOwnerId($existingJob->getOwnerId());
        $job->setName($existingJob->getName().'.1');
        $job->setUnique(true);
        $job->setStatus(Job::STATUS_NEW);
        $job->setCreatedAt(new \DateTime());

        $this->expectException(DuplicateJobException::class);
        $this->uniqueJobHandler->insert($this->getConnection(), $job);
    }

    public function testShouldThrowIfDuplicateJobNameWithPreSelect()
    {
        $this->uniqueJobHandler->setPreSelectSupport(true);

        $existingJob = $this->getReference(LoadJobData::JOB_1);

        $job = new Job();
        $job->setOwnerId($existingJob->getOwnerId().'.1');
        $job->setName($existingJob->getName());
        $job->setUnique(true);
        $job->setStatus(Job::STATUS_NEW);
        $job->setCreatedAt(new \DateTime());

        $this->expectException(DuplicateJobException::class);
        $this->uniqueJobHandler->insert($this->getConnection(), $job);
    }

    public function testShouldThrowIfDuplicateJobOwnerIdUsingUpsert()
    {
        $this->uniqueJobHandler->setUpsertSupport(true);

        $existingJob = $this->getReference(LoadJobData::JOB_1);

        $job = new Job();
        $job->setOwnerId($existingJob->getOwnerId());
        $job->setName($existingJob->getName().'.1');
        $job->setUnique(true);
        $job->setStatus(Job::STATUS_NEW);
        $job->setCreatedAt(new \DateTime());

        $this->expectException(DuplicateJobException::class);
        $this->uniqueJobHandler->insert($this->getConnection(), $job);
    }

    public function testShouldThrowIfDuplicateJobNameUsingUpsert()
    {
        $this->uniqueJobHandler->setUpsertSupport(true);

        $existingJob = $this->getReference(LoadJobData::JOB_1);

        $job = new Job();
        $job->setOwnerId($existingJob->getOwnerId().'.1');
        $job->setName($existingJob->getName());
        $job->setUnique(true);
        $job->setStatus(Job::STATUS_NEW);
        $job->setCreatedAt(new \DateTime());

        $this->expectException(DuplicateJobException::class);
        $this->uniqueJobHandler->insert($this->getConnection(), $job);
    }

    public function testShouldThrowIfDuplicateJobOwnerIdUsingUpsertAndPreSelect()
    {
        $this->uniqueJobHandler->setUpsertSupport(true);
        $this->uniqueJobHandler->setPreSelectSupport(true);

        $existingJob = $this->getReference(LoadJobData::JOB_1);

        $job = new Job();
        $job->setOwnerId($existingJob->getOwnerId());
        $job->setName($existingJob->getName().'.1');
        $job->setUnique(true);
        $job->setStatus(Job::STATUS_NEW);
        $job->setCreatedAt(new \DateTime());

        $this->expectException(DuplicateJobException::class);
        $this->uniqueJobHandler->insert($this->getConnection(), $job);
    }

    public function testShouldThrowIfDuplicateJobNameUsingUpsertAndPreSelect()
    {
        $this->uniqueJobHandler->setUpsertSupport(true);
        $this->uniqueJobHandler->setPreSelectSupport(true);

        $existingJob = $this->getReference(LoadJobData::JOB_1);

        $job = new Job();
        $job->setOwnerId($existingJob->getOwnerId().'.1');
        $job->setName($existingJob->getName());
        $job->setUnique(true);
        $job->setStatus(Job::STATUS_NEW);
        $job->setCreatedAt(new \DateTime());

        $this->expectException(DuplicateJobException::class);
        $this->uniqueJobHandler->insert($this->getConnection(), $job);
    }

    public function testShouldRemoveWhenFinished()
    {
        $existingJob = $this->getReference(LoadJobData::JOB_1);

        $this->uniqueJobHandler->delete($this->getConnection(), $existingJob);
    }
}
