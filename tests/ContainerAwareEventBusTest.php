<?php
declare(strict_types = 1);

namespace Tests\Innmind\EventBusBundle;

use Innmind\EventBusBundle\{
    ContainerAwareEventBus,
    Factory\ContainerAwareEventBusFactory
};
use Innmind\EventBus\{
    EventBusInterface,
    ClassName\ExtractorInterface,
    ClassName\InheritanceExtractor
};
use Innmind\Immutable\{
    Map,
    SetInterface
};
use Symfony\Component\DependencyInjection\{
    ContainerInterface,
    ContainerBuilder,
    Reference,
    Definition
};
use PHPUnit\Framework\TestCase;

class ContainerAwareEventBusTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            EventBusInterface::class,
            new ContainerAwareEventBus(
                $this->createMock(ContainerInterface::class),
                new Map('string', SetInterface::class),
                $this->createMock(ExtractorInterface::class)
            )
        );
    }

    /**
     * @expectedException Innmind\EventBusBundle\Exception\InvalidArgumentException
     */
    public function testThrowWhenInvalidListenersMap()
    {
        new ContainerAwareEventBus(
            $this->createMock(ContainerInterface::class),
            new Map('string', 'array'),
            $this->createMock(ExtractorInterface::class)
        );
    }

    /**
     * @expectedException Innmind\EventBus\Exception\InvalidArgumentException
     */
    public function testThrowWhenEventIsNotAnObject()
    {
        (new ContainerAwareEventBus(
            $this->createMock(ContainerInterface::class),
            new Map('string', SetInterface::class),
            $this->createMock(ExtractorInterface::class)
        ))->dispatch([]);
    }

    public function testDispatchWithCircularReference()
    {
        $container = new ContainerBuilder;
        $mock = $this->createMock(EventBusInterface::class);
        $listener = new class($container, $mock) {
            private $container;

            public function __construct(
                ContainerInterface $container,
                EventBusInterface $bus
            ) {
                $this->container = $container;
            }

            public function __invoke()
            {
                $this->container->setParameter('called', true);
            }
        };
        $container->setDefinition(
            'listener',
            new Definition(
                get_class($listener),
                [
                    new Reference('service_container'),
                    new Reference('event_bus'),
                ]
            )
        );
        $container->setDefinition(
            'event_bus',
            (new Definition(
                ContainerAwareEventBus::class,
                [
                    new Reference('service_container'),
                    ['stdClass' => ['listener']],
                    new Reference('extractor'),
                ]
            ))
                ->setFactory([ContainerAwareEventBusFactory::class, 'make'])
        );
        $container->setDefinition(
            'extractor',
            new Definition(InheritanceExtractor::class)
        );

        $this->assertFalse($container->hasParameter('called'));
        $this->assertSame(
            $container->get('event_bus'),
            $container->get('event_bus')->dispatch(new \stdClass)
        );
        $this->assertTrue($container->hasParameter('called'));
        $this->assertTrue($container->getParameter('called'));
    }
}
