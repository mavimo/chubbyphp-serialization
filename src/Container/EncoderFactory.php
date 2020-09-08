<?php

declare(strict_types=1);

namespace Chubbyphp\Serialization\Container;

use Chubbyphp\Serialization\Encoder\Encoder;
use Chubbyphp\Serialization\Encoder\EncoderInterface;
use Chubbyphp\Serialization\Encoder\TypeEncoderInterface;
use Psr\Container\ContainerInterface;

/**
 * @deprecated \Chubbyphp\Serialization\ServiceFactory\EncoderFactory
 */
final class EncoderFactory
{
    public function __invoke(ContainerInterface $container): EncoderInterface
    {
        return new Encoder(
            $container->get(TypeEncoderInterface::class.'[]')
        );
    }
}
