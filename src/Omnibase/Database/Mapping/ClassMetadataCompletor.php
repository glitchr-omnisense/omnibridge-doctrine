<?php

namespace Omnibridge\Doctrine\Omnibase\Database\Mapping;

/*
 * On omnisense/omnibase 5.x, the core's Base\Database\Mapping\Completor under its former name (plan "omnibase agnostic",
 * step E3): what omnibase's attributes add to a class's mapping is read from the class itself (Base\Database\Mapping\
 * Completors), on no ORM, and ClassMetadataManipulator answers it from there; the name stays for the code that types on
 * it. On 4.x, which this bridge serves too, the class as it was.
 */
if (class_exists(\Base\Database\Mapping\Completor::class)) {
    class_alias(\Base\Database\Mapping\Completor::class, ClassMetadataCompletor::class);
} else {
    class ClassMetadataCompletor
    {
        protected ?string $className = null;
        protected array $payload = [];

        public function __construct(string $className, array $payload = [])
        {
            $this->className = $className;
            $this->payload = $payload;
        }

        public function getName(): ?string
        {
            return $this->className;
        }

        /**
         * @param $name
         * @return bool
         */
        public function exists($name)
        {
            return array_key_exists($name, $this->payload);
        }

        /**
         * @param $name
         * @return mixed
         */
        public function &__get($name)
        {
            return $this->payload[$name];
        }

        /**
         * @param string $name
         * @param mixed $value
         * @return $this
         */
        public function __set(string $name, mixed $value)
        {
            $this->payload["class"] ??= $this->className; // Useful when looking inside cache directory for debugging.....
            $this->payload[$name] = $value;
            return $this;
        }
}
}
