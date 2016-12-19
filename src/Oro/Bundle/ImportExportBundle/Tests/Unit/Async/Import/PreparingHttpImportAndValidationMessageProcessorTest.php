<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Async\Import;


use Oro\Bundle\ImportExportBundle\Async\Import\AbstractPreparingHttpImportMessageProcessor;
use Oro\Bundle\ImportExportBundle\Async\Import\PreparingHttpImportMessageProcessor;
use Oro\Bundle\ImportExportBundle\Async\Import\PreparingHttpImportValidationMessageProcessor;
use Oro\Bundle\ImportExportBundle\File\SplitterCsvFile;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\DependentJobContext;
use Oro\Component\MessageQueue\Job\DependentJobService;
use Oro\Bundle\ImportExportBundle\Async\Topics;

use Oro\Component\MessageQueue\Job\Job;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;

use Oro\Bundle\ImportExportBundle\Handler\HttpImportHandler;
use Oro\Bundle\UserBundle\Entity\Repository\UserRepository;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\MessageQueue\Job\JobRunner;

class PreparingHttpImportMessageProcessorTest extends \PHPUnit_Framework_TestCase
{
    public function testImportProcessCanBeConstructedWithRequiredAttributes()
    {
        $chunkHttpImportMessageProcessor = new PreparingHttpImportMessageProcessor(
            $this->createHttpImportHandlerMock(),
            $this->createJobRunnerMock(),
            $this->createMessageProducerInterfaceMock(),
            $this->createLoggerInterfaceMock(),
            $this->createSplitterCsvFileMock(),
            $this->createDoctrineMock(),
            $this->createDependentJobMock()
        );

        $this->assertInstanceOf(AbstractPreparingHttpImportMessageProcessor::class, $chunkHttpImportMessageProcessor);
        $this->assertInstanceOf(MessageProcessorInterface::class, $chunkHttpImportMessageProcessor);
        $this->assertInstanceOf(TopicSubscriberInterface::class, $chunkHttpImportMessageProcessor);
    }

    public function testImportProcessShouldReturnSubscribedTopics()
    {
        $expectedSubscribedTopics = [Topics::IMPORT_HTTP_PREPARING,];
        $this->assertEquals($expectedSubscribedTopics, PreparingHttpImportMessageProcessor::getSubscribedTopics());
    }

    public function testShouldLogErrorAndRejectMessageIfMessageWasInvalid()
    {
        $logger = $this->createLoggerInterfaceMock();
        $logger
            ->expects($this->once())
            ->method('critical')
            ->with('Got invalid message. body: []')
        ;

        $processor = new PreparingHttpImportMessageProcessor(
            $this->createHttpImportHandlerMock(),
            $this->createJobRunnerMock(),
            $this->createMessageProducerInterfaceMock(),
            $logger,
            $this->createSplitterCsvFileMock(),
            $this->createDoctrineMock(),
            $this->createDependentJobMock()
        );

        $message = $this->createMessageMock();
        $message
            ->expects($this->exactly(2))
            ->method('getBody')
            ->willReturn('[]')
        ;

        $result = $processor->process($message, $this->createSessionMock());
        $this->assertEquals(MessageProcessorInterface::REJECT, $result);
    }

    public function testShouldLogErrorAndRejectMessageIfUserNotFound()
    {
        $logger = $this->createLoggerInterfaceMock();
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('User not found. id: 1')
        ;

        $userRepo = $this->createUserRepositoryMock();
        $userRepo
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn(null);
        ;

        $doctrine = $this->createDoctrineMock();
        $doctrine
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->will($this->returnValue($userRepo))
        ;

        $processor = new PreparingHttpImportMessageProcessor(
            $this->createHttpImportHandlerMock(),
            $this->createJobRunnerMock(),
            $this->createMessageProducerInterfaceMock(),
            $logger,
            $this->createSplitterCsvFileMock(),
            $doctrine,
            $this->createDependentJobMock()
        );

        $message = $this->createMessageMock();
        $message
            ->expects($this->once())
            ->method('getBody')
            ->willReturn(json_encode([
                        'filePath' => 'test.csv',
                        'userId' => '1',
                        'jobId' => '1',
                        'jobName' => 'test',
                        'processorAlias' => 'test',
                        'options' => [],
                    ]))
        ;

        $result = $processor->process($message, $this->createSessionMock());
        $this->assertEquals(MessageProcessorInterface::REJECT, $result);
    }

    public function testShouldRunRunUniqueAndACKMessage()
    {
        $user = new User();
        $user->setId(1);
        $user->setFirstName('John');
        $organization = new Organization();
        $organization->setId(1);
        $user->setOrganization($organization);

        $userRepo = $this->createUserRepositoryMock();
        $userRepo
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);
        ;

        $doctrine = $this->createDoctrineMock();
        $doctrine
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->will($this->returnValue($userRepo))
        ;

        $jobRunner = $this->createJobRunnerMock();
        $jobRunner
            ->expects($this->once())
            ->method('runUnique')
            ->with(1)
            ->willReturn(true)
            ;

        $processor = new PreparingHttpImportMessageProcessor(
            $this->createHttpImportHandlerMock(),
            $jobRunner,
            $this->createMessageProducerInterfaceMock(),
            $this->createLoggerInterfaceMock(),
            $this->createSplitterCsvFileMock(),
            $doctrine,
            $this->createDependentJobMock()
        );

        $message = $this->createMessageMock();
        $message
            ->expects($this->once())
            ->method('getBody')
            ->willReturn(json_encode([
                        'filePath' => 'test.csv',
                        'userId' => '1',
                        'jobId' => '1',
                        'jobName' => 'test',
                        'processorAlias' => 'test',
                        'options' => [],
                    ]))
        ;

        $message
            ->expects($this->once())
            ->method('getMessageId')
            ->willReturn(1);

        $result = $processor->process($message, $this->createSessionMock());
        $this->assertEquals(MessageProcessorInterface::ACK, $result);
    }

    public function testShouldProcessPreparingMessageAndSendImportAndNotificationMessagesAndACKMessage()
    {
        $messageData = [
            'filePath' => 'test.csv',
            'userId' => '1',
            'jobId' => '1',
            'jobName' => 'test',
            'originFileName' => 'test.csv',
            'processorAlias' => 'processor_test',
            'options' => [],
        ];
        $job = new Job();
        $job->setId(1);
        $childJob1 = new Job();
        $childJob1->setId(2);
        $childJob1->setRootJob($job);
        $childJob2 = new Job();
        $childJob2->setId(3);
        $childJob2->setRootJob($job);
        $childJob = new Job();
        $childJob->setId(10);
        $childJob->setRootJob($job);
        $user = new User();
        $user->setId(1);
        $user->setFirstName('John');
        $organization = new Organization();
        $organization->setId(1);
        $user->setOrganization($organization);

        $userRepo = $this->createUserRepositoryMock();
        $userRepo
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);
        ;

        $doctrine = $this->createDoctrineMock();
        $doctrine
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->will($this->returnValue($userRepo))
        ;
        $jobRunner = $this->createJobRunnerMock();
        $jobRunner
            ->expects($this->once())
            ->method('runUnique')
            ->with(1, 'oro:import:http:processor_test:1')
            ->will($this->returnCallback(function ($jobId, $name, $callback) use ($jobRunner, $childJob) {
                        return $callback($jobRunner, $childJob);
                    }
                )
            )
        ;
        $jobRunner
            ->expects($this->at(0))
            ->method('createDelayed')
            ->with('oro:import:http:processor_test1:chunk.1')
            ->will($this->returnCallback(function ($jobId,  $callback) use ($jobRunner, $childJob1) {
                        return $callback($jobRunner, $childJob1);
                    }
                )
            );
        $jobRunner
            ->expects($this->at(1))
            ->method('createDelayed')
            ->with('oro:import:http:processor_test1:chunk.2')
            ->will($this->returnCallback(function ($jobId,  $callback) use ($jobRunner, $childJob2) {
                        return $callback($jobRunner, $childJob2);
                    }
                )
            )
        ;

        $csvSplitter = $this->createSplitterCsvFileMock();
        $csvSplitter
            ->expects($this->once())
            ->method('getSplitFiles')
            ->with('test.csv')
            ->willReturn(['1_test.csv', '2_test.csv'])
            ;
        $messageData1 = $messageData;
        $messageData1['filePath'] = '1_test.csv';
        $messageData1['jobId'] = 2;
        $messageData2 = $messageData;
        $messageData2['filePath'] = '2_test.csv';
        $messageData2['jobId'] = 3;

        $producer = $this->createMessageProducerInterfaceMock();
        $producer
            ->expects($this->exactly(2))
            ->method('send')
            ->withConsecutive(
                ['oro.importexport.import_http', $messageData1],
                ['oro.importexport.import_http', $messageData2]
            )
        ;

        $dependentContext = $this->createDependentJobContextMock();
        $dependentContext
            ->expects($this->once())
            ->method('addDependentJob')
            ->with('oro.importexport.send_notification')
        ;

        $dependentJob = $this->createDependentJobMock();
        $dependentJob
            ->expects($this->once())
            ->method('createDependentJobContext')
            ->with($job)
            ->willReturn($dependentContext)
        ;
        $dependentJob
            ->expects($this->once())
            ->method('saveDependentJob')
            ->with($dependentContext)
        ;

        $processor = new PreparingHttpImportMessageProcessor(
            $this->createHttpImportHandlerMock(),
            $jobRunner,
            $producer,
            $this->createLoggerInterfaceMock(),
            $csvSplitter,
            $doctrine,
            $dependentJob
        );

        $message = $this->createMessageMock();
        $message
            ->expects($this->once())
            ->method('getBody')
            ->willReturn(json_encode($messageData))
        ;

        $message
            ->expects($this->once())
            ->method('getMessageId')
            ->willReturn(1);

        $result = $processor->process($message, $this->createSessionMock());
        $this->assertEquals(MessageProcessorInterface::ACK, $result);

    }

    public function testIValidationImportProcessCanBeConstructedWithRequiredAttributes()
    {
        $chunkHttpImportMessageProcessor = new PreparingHttpImportValidationMessageProcessor(
            $this->createHttpImportHandlerMock(),
            $this->createJobRunnerMock(),
            $this->createMessageProducerInterfaceMock(),
            $this->createLoggerInterfaceMock(),
            $this->createSplitterCsvFileMock(),
            $this->createDoctrineMock(),
            $this->createDependentJobMock()
        );

        $this->assertInstanceOf(AbstractPreparingHttpImportMessageProcessor::class, $chunkHttpImportMessageProcessor);
        $this->assertInstanceOf(MessageProcessorInterface::class, $chunkHttpImportMessageProcessor);
        $this->assertInstanceOf(TopicSubscriberInterface::class, $chunkHttpImportMessageProcessor);
    }

    public function testValidationImportProcessShouldReturnSubscribedTopics()
    {
        $expectedSubscribedTopics = [Topics::IMPORT_HTTP_VALIDATION_PREPARING,];
        $this->assertEquals($expectedSubscribedTopics, PreparingHttpImportValidationMessageProcessor::getSubscribedTopics());
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|HttpImportHandler
     */
    protected function createHttpImportHandlerMock()
    {
        return $this->getMock(HttpImportHandler::class, [], [], '', false);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|JobRunner
     */
    protected function createJobRunnerMock()
    {
        return $this->getMock(JobRunner::class, [], [], '', false);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|MessageProducerInterface
     */
    protected function createMessageProducerInterfaceMock()
    {
        return $this->getMock(MessageProducerInterface::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|LoggerInterface
     */
    protected function createLoggerInterfaceMock()
    {
        return $this->getMock(LoggerInterface::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|SplitterCsvFile
     */
    protected function createSplitterCsvFileMock()
    {
        return $this->getMock(SplitterCsvFile::class, [], [], '', false);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|RegistryInterface
     */
    protected function createDoctrineMock()
    {
        return $this->getMock(RegistryInterface::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|DependentJobService
     */
    protected function createDependentJobMock()
    {
        return $this->getMock(DependentJobService::class, [], [], '', false);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|MessageInterface
     */
    private function createMessageMock()
    {
        return $this->getMock(MessageInterface::class, [], [], '', false);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|SessionInterface
     */
    private function createSessionMock()
    {
        return $this->getMock(SessionInterface::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|UserRepository
     */
    private function createUserRepositoryMock()
    {
        return $this->getMock(UserRepository::class, [], [], '', false);
    }
    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|DependentJobContext
     */
    private function createDependentJobContextMock()
    {
        return $this->getMock(DependentJobContext::class, [], [], '', false);
    }
}
