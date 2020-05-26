<?php

namespace GoetasWebservices\SoapServices\SoapClient\Result;

use JMS\Serializer\Serializer;

class ResultCreator implements ResultCreatorInterface
{
    private $unwrap = false;
    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(Serializer $serializer, $unwrap = false)
    {
        $this->serializer = $serializer;
        $this->unwrap = $unwrap;
    }

    public function prepareResult($object, array $output)
    {
        if (!count($output['parts'])) {
            return null;
        }

        if (!method_exists($this->serializer, 'getMetadataFactory')) {
            return $this->prepareResultSerializerV2($object, $output);
        }

        $factory = $this->serializer->getMetadataFactory();

        $classMetadata = $factory->getMetadataForClass($output['message_fqcn']);
        $bodyMetadata = $classMetadata->propertyMetadata['body'];
        $bodyClassMetadata = $factory->getMetadataForClass($bodyMetadata->type['name']);
        $body = $bodyMetadata->getValue($object);
        $parts = [];
        foreach ($bodyClassMetadata->propertyMetadata as $propertyMetadata) {
            $parts[$propertyMetadata->name] = $propertyMetadata->getValue($body);
        }
        if (count($output['parts']) > 1) {
            return $parts;
        } else {
            if ($this->unwrap) {
                foreach ($bodyClassMetadata->propertyMetadata as $propertyMetadata) {
                    $propClassMetadata = $factory->getMetadataForClass($propertyMetadata->type['name']);

                    if (count($propClassMetadata->propertyMetadata) > 1) {
                        throw new \Exception("When using wrapped mode, the wrapped object can not have multiple properties");
                    }
                    if (!count($propClassMetadata->propertyMetadata)) {
                        return null;
                    }
                    $propertyMetadata = reset($propClassMetadata->propertyMetadata);
                    return $propertyMetadata->getValue(reset($parts));
                }
            }
            return reset($parts);
        }
    }

    /**
     * More recent version of JMS/Serializer don't have a getMetadataFactory() method.
     * Call the getBody()->getFooResponse() method manually on the envelope body
     *
     * @param       $object
     * @param array $output
     *
     * @return object
     */
    private function prepareResultSerializerV2($object, array $output)
    {
        $method = sprintf('get%s', key($output['parts']));

        return $object->getBody()->$method();
    }
}
