<?php

declare(strict_types=1);

namespace Chubbyphp\Serialization\Normalizer;

use Chubbyphp\Serialization\Mapping\NormalizationFieldMappingInterface;
use Chubbyphp\Serialization\Mapping\NormalizationLinkMappingInterface;
use Chubbyphp\Serialization\Mapping\NormalizationObjectMappingInterface;
use Chubbyphp\Serialization\SerializerLogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class Normalizer implements NormalizerInterface
{
    /**
     * @var NormalizerObjectMappingRegistryInterface
     */
    private $normalizerObjectMappingRegistry;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        NormalizerObjectMappingRegistryInterface $normalizerObjectMappingRegistry,
        ?LoggerInterface $logger = null
    ) {
        $this->normalizerObjectMappingRegistry = $normalizerObjectMappingRegistry;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @throws SerializerLogicException
     *
     * @return array<string, array|string|float|int|bool|null>
     */
    public function normalize(
        object $object,
        ?NormalizerContextInterface $context = null,
        string $path = ''
    ): array {
        $context = $context ?? NormalizerContextBuilder::create()->getContext();

        $class = get_class($object);
        $objectMapping = $this->getObjectMapping($class);

        $fieldMappings = $objectMapping->getNormalizationFieldMappings($path);

        $data = $this->getFieldsByFieldNormalizationMappings($context, $fieldMappings, $path, $object);

        $embeddedMappings = $objectMapping->getNormalizationEmbeddedFieldMappings($path);
        $embedded = $this->getFieldsByFieldNormalizationMappings($context, $embeddedMappings, $path, $object);

        $linkMappings = $objectMapping->getNormalizationLinkMappings($path);
        $links = $this->getLinksByLinkNormalizationMappings($context, $linkMappings, $path, $object);

        if ([] !== $embedded) {
            $data['_embedded'] = $embedded;
        }

        if ([] !== $links) {
            $data['_links'] = $links;
        }

        if (null !== $type = $objectMapping->getNormalizationType()) {
            $data['_type'] = $type;
        }

        return $data;
    }

    /**
     * @throws SerializerLogicException
     */
    private function getObjectMapping(string $class): NormalizationObjectMappingInterface
    {
        try {
            return $this->normalizerObjectMappingRegistry->getObjectMapping($class);
        } catch (SerializerLogicException $exception) {
            $this->logger->error('serialize: {exception}', ['exception' => $exception->getMessage()]);

            throw $exception;
        }
    }

    /**
     * @param array<int, NormalizationFieldMappingInterface> $normalizationFieldMappings
     *
     * @return array<string, mixed>
     */
    private function getFieldsByFieldNormalizationMappings(
        NormalizerContextInterface $context,
        array $normalizationFieldMappings,
        string $path,
        object $object
    ): array {
        $data = [];
        foreach ($normalizationFieldMappings as $normalizationFieldMapping) {
            $name = $normalizationFieldMapping->getName();

            $subPath = $this->getSubPathByName($path, $name);

            if (!$this->isCompliant($subPath, $object, $context, $normalizationFieldMapping)) {
                continue;
            }

            $fieldNormalizer = $normalizationFieldMapping->getFieldNormalizer();

            $this->logger->info('serialize: path {path}', ['path' => $subPath]);

            $data[$name] = $fieldNormalizer->normalizeField($subPath, $object, $context, $this);
        }

        return $data;
    }

    /**
     * @param array<int, NormalizationLinkMappingInterface> $normalizationLinkMappings
     *
     * @return array<string, mixed>
     */
    private function getLinksByLinkNormalizationMappings(
        NormalizerContextInterface $context,
        array $normalizationLinkMappings,
        string $path,
        object $object
    ): array {
        $links = [];
        foreach ($normalizationLinkMappings as $normalizationLinkMapping) {
            $name = $normalizationLinkMapping->getName();

            $subPath = $this->getSubPathByName($path, $name);

            if (!$this->isCompliant($subPath, $object, $context, $normalizationLinkMapping)) {
                continue;
            }

            $linkNormalizer = $normalizationLinkMapping->getLinkNormalizer();

            $links[$name] = $linkNormalizer->normalizeLink($path, $object, $context);
        }

        return $links;
    }

    /**
     * @param NormalizationFieldMappingInterface|NormalizationLinkMappingInterface $mapping
     */
    private function isCompliant(string $path, object $object, NormalizerContextInterface $context, $mapping): bool
    {
        return $mapping->getPolicy()->isCompliantIncludingPath($path, $object, $context);
    }

    private function getSubPathByName(string $path, string $name): string
    {
        return '' === $path ? $name : $path.'.'.$name;
    }
}
