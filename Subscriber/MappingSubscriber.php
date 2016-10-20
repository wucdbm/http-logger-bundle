<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Platforms;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Id\BigIntegerIdentityGenerator;
use Doctrine\ORM\Id\IdentityGenerator;
use Doctrine\ORM\Id\SequenceGenerator;
use Doctrine\ORM\Id\UuidGenerator;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMException;

/**
 * Translatable Doctrine2 subscriber.
 *
 * Provides mapping for translatable entities and their translations.
 */
class MappingSubscriber implements EventSubscriber {

    protected $configs;

    protected $map = [];

    public function __construct($configs) {
        $this->configs = $configs;
        foreach ($configs as $name => $config) {
            $this->map[$config['log_class']] = $name;
            $this->map[$config['log_exception_class']] = $name;
            $this->map[$config['log_message_class']] = $name;
            $this->map[$config['log_message_type_class']] = $name;
        }
    }

    protected function getConfigForClass($class) {
        $key = $this->map[$class];

        return $this->configs[$key];
    }

    /**
     * Returns hash of events, that this subscriber is bound to.
     *
     * @return array
     */
    public function getSubscribedEvents() {
        return [
            Events::loadClassMetadata
        ];
    }

    /**
     * Adds mapping to the translatable and translations.
     *
     * @param LoadClassMetadataEventArgs $eventArgs The event arguments
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs) {
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $eventArgs->getClassMetadata();
        /** @var EntityManager $entityManager */
        $entityManager = $eventArgs->getEntityManager();

        /** @var \ReflectionClass $reflectionClass */
        $reflectionClass = $classMetadata->getReflectionClass();

        if (!$reflectionClass) {
            return;
        }

        if ($this->shouldMapRequestLog($reflectionClass->getName())) {
            $this->mapRequestLog($classMetadata, $entityManager);
        }

        if ($this->shouldMapRequestLogMessage($reflectionClass->getName())) {
            $this->mapRequestLogMessage($classMetadata, $entityManager);
        }

        if ($this->shouldMapRequestLogMessageType($reflectionClass->getName())) {
            $this->mapRequestLogMessageType($classMetadata, $entityManager);
        }

        if ($this->shouldMapRequestLogException($reflectionClass->getName())) {
            $this->mapRequestLogException($classMetadata, $entityManager);
        }
    }

    protected function shouldMapRequestLog($className) {
        return $this->shouldMap($className, 'log_class');
    }

    protected function shouldMapRequestLogMessage($className) {
        return $this->shouldMap($className, 'log_message_class');
    }

    protected function shouldMapRequestLogMessageType($className) {
        return $this->shouldMap($className, 'log_message_type_class');
    }

    protected function shouldMapRequestLogException($className) {
        return $this->shouldMap($className, 'log_exception_class');
    }

    private function shouldMap($className, $configKey) {
        foreach ($this->configs as $configName => $config) {
            if ($className === $config[$configKey]) {
                return true;
            }
        }

        return false;
    }

    private function mapId(ClassMetadata $metadata, EntityManager $em) {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->createField('id', 'integer')->makePrimaryKey()->generatedValue()->option('unsigned', true)->build();

        $platform = $em->getConnection()->getDatabasePlatform();

        $idGenType = $metadata->generatorType;
        if ($idGenType == ClassMetadata::GENERATOR_TYPE_AUTO) {
            if ($platform->prefersSequences()) {
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_SEQUENCE);
            } else if ($platform->prefersIdentityColumns()) {
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
            } else {
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_TABLE);
            }
        }

        // Copied this shit over from the DoctrineBehaviors Translatable bundle from KNP
        // Basically, all that needs to be done here is to add IdentityGenerator to $metadata
        // But I'll keep this here just in case anybody uses a different environment

        $class = $metadata;

        // Create & assign an appropriate ID generator instance
        switch ($class->generatorType) {
            case ClassMetadata::GENERATOR_TYPE_IDENTITY:
                // For PostgreSQL IDENTITY (SERIAL) we need a sequence name. It defaults to
                // <table>_<column>_seq in PostgreSQL for SERIAL columns.
                // Not pretty but necessary and the simplest solution that currently works.
                $sequenceName = null;
                $fieldName = $class->identifier ? $class->getSingleIdentifierFieldName() : null;

                if ($platform instanceof Platforms\PostgreSQLPlatform) {
                    $columnName = $class->getSingleIdentifierColumnName();
                    $quoted = isset($class->fieldMappings[$fieldName]['quoted']) || isset($class->table['quoted']);
                    $sequenceName = $class->getTableName() . '_' . $columnName . '_seq';
                    $definition = array(
                        'sequenceName' => $platform->fixSchemaElementName($sequenceName)
                    );

                    if ($quoted) {
                        $definition['quoted'] = true;
                    }

                    $sequenceName = $em->getConfiguration()->getQuoteStrategy()->getSequenceName($definition, $class, $platform);
                }

                $generator = ($fieldName && $class->fieldMappings[$fieldName]['type'] === 'bigint')
                    ? new BigIntegerIdentityGenerator($sequenceName)
                    : new IdentityGenerator($sequenceName);

                $class->setIdGenerator($generator);

                break;

            case ClassMetadata::GENERATOR_TYPE_SEQUENCE:
                // If there is no sequence definition yet, create a default definition
                $definition = $class->sequenceGeneratorDefinition;

                if (!$definition) {
                    $fieldName = $class->getSingleIdentifierFieldName();
                    $columnName = $class->getSingleIdentifierColumnName();
                    $quoted = isset($class->fieldMappings[$fieldName]['quoted']) || isset($class->table['quoted']);
                    $sequenceName = $class->getTableName() . '_' . $columnName . '_seq';
                    $definition = array(
                        'sequenceName'   => $platform->fixSchemaElementName($sequenceName),
                        'allocationSize' => 1,
                        'initialValue'   => 1,
                    );

                    if ($quoted) {
                        $definition['quoted'] = true;
                    }

                    $class->setSequenceGeneratorDefinition($definition);
                }

                $sequenceGenerator = new SequenceGenerator(
                    $em->getConfiguration()->getQuoteStrategy()->getSequenceName($definition, $class, $platform),
                    $definition['allocationSize']
                );
                $class->setIdGenerator($sequenceGenerator);
                break;

            case ClassMetadata::GENERATOR_TYPE_NONE:
                $class->setIdGenerator(new AssignedGenerator());
                break;

            case ClassMetadata::GENERATOR_TYPE_UUID:
                $class->setIdGenerator(new UuidGenerator());
                break;

            case ClassMetadata::GENERATOR_TYPE_TABLE:
                throw new ORMException("TableGenerator not yet implemented.");
                break;

            case ClassMetadata::GENERATOR_TYPE_CUSTOM:
                $definition = $class->customGeneratorDefinition;
                if (!class_exists($definition['class'])) {
                    throw new ORMException("Can't instantiate custom generator : " .
                        $definition['class']);
                }
                $class->setIdGenerator(new $definition['class']);
                break;

            default:
                throw new ORMException("Unknown generator type: " . $class->generatorType);
        }
    }

    protected function getRequestLogTable($config) {
        return $this->getTableName($config, 'request_logs');
    }

    protected function getRequestLogMessageTable($config) {
        return $this->getTableName($config, 'request_logs_messages');
    }

    protected function getRequestLogMessageTypeTable($config) {
        return $this->getTableName($config, 'request_logs_messages_types');
    }

    protected function getRequestLogExceptionTable($config) {
        return $this->getTableName($config, 'request_logs_exceptions');
    }

    protected function getTableName($config, $name) {
        return [
            'name' => sprintf('%s%s', $config['table_prefix'], $name)
        ];
    }

    protected function mapRequestLog(ClassMetadata $metadata, EntityManager $em) {
        $reflection = $metadata->getReflectionClass();
        $config = $this->getConfigForClass($reflection->getName());

        $metadata->setPrimaryTable($this->getRequestLogTable($config));

//        /**
//         * @ORM\Id
//         * @ORM\Column(name="id", type="integer", options={"unsigned"=true})
//         * @ORM\GeneratedValue(strategy="AUTO")
//         */
//        protected $id;
        if (!$metadata->hasAssociation('id')) {
            $this->mapId($metadata, $em);
        }

//        /**
//         * @ORM\Column(name="status_code", type="smallint", nullable=true, options={"unsigned"=true})
//         */
//        protected $statusCode;
        if (!$metadata->hasAssociation('statusCode')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('statusCode', 'smallint')->nullable(true)->option('unsigned', true)->build();
        }

//        /**
//         * @ORM\Column(name="url", type="text", nullable=true)
//         */
//        protected $url;
        if (!$metadata->hasAssociation('url')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('url', 'text')->nullable(true)->build();
        }

//        /**
//         * @ORM\Column(name="method", type="string", nullable=true)
//         */
//        protected $method;
        if (!$metadata->hasAssociation('method')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('method', 'string')->nullable(true)->build();
        }

//        /**
//         * @ORM\Column(name="date", type="datetime", nullable=false)
//         */
//        protected $date;
        if (!$metadata->hasAssociation('date')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('date', 'datetime')->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="message", type="string", nullable=false)
//         */
//        protected $message;
        if (!$metadata->hasAssociation('message')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('message', 'string')->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="debug", type="string", nullable=true)
//         */
//        protected $debug;
        if (!$metadata->hasAssociation('debug')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('debug', 'string')->nullable(true)->build();
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\BookingLogMessage", inversedBy="requestTo", orphanRemoval=true)
//         * @ORM\JoinColumn(name="request_id", referencedColumnName="id", onDelete="CASCADE", nullable=true)
//         */
//        protected $request;
        if (!$metadata->hasAssociation('request')) {
            $metadata->mapOneToOne([
                'fieldName'    => 'request',
                'inversedBy'   => 'requestTo',
                'cascade'      => ['persist', 'merge'],
                'fetch'        => 'EAGER',
                'joinColumns'  => [[
                    'name'                 => 'request_id',
                    'referencedColumnName' => 'id',
                    'nullable'             => true
                ]],
                'targetEntity' => $config['log_message_class']
            ]);
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\BookingLogMessage", inversedBy="responseTo", orphanRemoval=true)
//         * @ORM\JoinColumn(name="response_id", referencedColumnName="id", onDelete="CASCADE", nullable=true)
//         */
//        protected $response;
        if (!$metadata->hasAssociation('response')) {
            $metadata->mapOneToOne([
                'fieldName'    => 'response',
                'inversedBy'   => 'responseTo',
                'cascade'      => ['persist', 'merge'],
                'fetch'        => 'EAGER',
                'joinColumns'  => [[
                    'name'                 => 'response_id',
                    'referencedColumnName' => 'id',
                    'nullable'             => true
                ]],
                'targetEntity' => $config['log_message_class']
            ]);
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLogException", inversedBy="log", orphanRemoval=true)
//         * @ORM\JoinColumn(name="exception_id", referencedColumnName="id", onDelete="CASCADE", nullable=true)
//         */
//        protected $exception;
        if (!$metadata->hasAssociation('exception')) {
            $metadata->mapOneToOne([
                'fieldName'     => 'exception',
                'inversedBy'    => 'log',
                'orphanRemoval' => true,
                'cascade'       => ['persist', 'merge'],
                'fetch'         => 'EAGER',
                'joinColumns'   => [[
                    'name'                 => 'exception_id',
                    'referencedColumnName' => 'id',
                    'nullable'             => true
                ]],
                'targetEntity'  => $config['log_exception_class']
            ]);
        }
    }

    protected function mapRequestLogMessage(ClassMetadata $metadata, EntityManager $em) {
        $reflection = $metadata->getReflectionClass();
        $config = $this->getConfigForClass($reflection->getName());

        $metadata->setPrimaryTable($this->getRequestLogMessageTable($config));

//        /**
//         * @ORM\Id
//         * @ORM\Column(name="id", type="integer", options={"unsigned"=true})
//         * @ORM\GeneratedValue(strategy="AUTO")
//         */
//        protected $id;
        if (!$metadata->hasAssociation('id')) {
            $this->mapId($metadata, $em);
        }

//        /**
//         * @ORM\Column(name="content", type="text", length=16777215, nullable=false)
//         */
//        protected $content;
        if (!$metadata->hasAssociation('content')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('content', 'text')->length(16777215)->nullable(false)->build();
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLog", mappedBy="request")
//         */
//        protected $requestTo;
        if (!$metadata->hasAssociation('requestTo')) {
            $metadata->mapOneToOne([
                'fieldName'    => 'requestTo',
                'mappedBy'     => 'request',
                'cascade'      => ['persist', 'merge', 'remove'],
                'fetch'        => 'EAGER',
                'targetEntity' => $config['log_class']
            ]);
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLog", mappedBy="response")
//         */
//        protected $responseTo;
        if (!$metadata->hasAssociation('responseTo')) {
            $metadata->mapOneToOne([
                'fieldName'    => 'responseTo',
                'mappedBy'     => 'response',
                'cascade'      => ['persist', 'merge', 'remove'],
                'fetch'        => 'EAGER',
                'targetEntity' => $config['log_class']
            ]);
        }

//        /**
//         * @ORM\ManyToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLogMessageType", inversedBy="messages")
//         * @ORM\JoinColumn(name="type_id", referencedColumnName="id", nullable=false)
//         */
//        protected $type;
        if (!$metadata->hasAssociation('type')) {
            $metadata->mapManyToOne([
                'fieldName'    => 'type',
                'inversedBy'   => 'messages',
                'fetch'        => 'EAGER',
                'joinColumns'  => [[
                    'name'                 => 'type_id',
                    'referencedColumnName' => 'id',
                    'nullable'             => false
                ]],
                'targetEntity' => $config['log_message_type_class']
            ]);
        }

//        /**
//         * @ORM\Column(name="date", type="datetime", nullable=false)
//         */
//        protected $date;
        if (!$metadata->hasAssociation('date')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('date', 'datetime')->nullable(false)->build();
        }
    }

    protected function mapRequestLogMessageType(ClassMetadata $metadata, EntityManager $em) {
        $reflection = $metadata->getReflectionClass();
        $config = $this->getConfigForClass($reflection->getName());

        $metadata->setPrimaryTable($this->getRequestLogMessageTypeTable($config));

//        /**
//         * @ORM\Id
//         * @ORM\Column(name="id", type="integer", options={"unsigned"=true})
//         * @ORM\GeneratedValue(strategy="AUTO")
//         */
//        protected $id;
        if (!$metadata->hasAssociation('id')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('id', 'integer')->makePrimaryKey()->option('unsigned', true)->build();
//            $this->mapId($metadata, $em);
        }

//        /**
//         * @ORM\Column(name="name", type="string", nullable=false)
//         */
//        protected $name;
        if (!$metadata->hasAssociation('name')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('name', 'string')->nullable(false)->build();
        }

//        /**
//         * @ORM\OneToMany(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLogMessage", mappedBy="type")
//         */
//        protected $messages;
        if (!$metadata->hasAssociation('messages')) {
            $metadata->mapOneToMany([
                'fieldName'    => 'messages',
                'mappedBy'     => 'type',
                'fetch'        => 'EAGER',
                'targetEntity' => $config['log_message_class'],
            ]);
        }
    }

    protected function mapRequestLogException(ClassMetadata $metadata, EntityManager $em) {
        $reflection = $metadata->getReflectionClass();
        $config = $this->getConfigForClass($reflection->getName());

        $metadata->setPrimaryTable($this->getRequestLogExceptionTable($config));

//        /**
//         * @ORM\Id
//         * @ORM\Column(name="id", type="integer", options={"unsigned"=true})
//         * @ORM\GeneratedValue(strategy="AUTO")
//         */
//        protected $id;
        if (!$metadata->hasAssociation('id')) {
            $this->mapId($metadata, $em);
        }

//        /**
//         * @ORM\Column(name="message", type="text", nullable=true)
//         */
//        protected $message;

        if (!$metadata->hasAssociation('message')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('message', 'text')->nullable(true)->build();
        }

//        /**
//         * @ORM\Column(name="code", type="string", nullable=true)
//         */
//        protected $code;
        if (!$metadata->hasAssociation('code')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('code', 'string')->nullable(true)->build();
        }
//
//        /**
//         * @ORM\Column(name="file", type="text", nullable=false)
//         */
//        protected $file;
        if (!$metadata->hasAssociation('file')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('file', 'text')->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="line", type="integer", options={"unsigned"=true}, nullable=false)
//         */
//        protected $line;
        if (!$metadata->hasAssociation('line')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('line', 'integer')->nullable(false)->option('unsigned', true)->build();
        }

//        /**
//         * @ORM\Column(name="stack_trace_string", type="text", length=16777215, nullable=false)
//         */
//        protected $stackTraceString;
        if (!$metadata->hasAssociation('stackTraceString')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('stackTraceString', 'text')->length(16777215)->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="json", type="text", length=16777215, nullable=false)
//         */
//        protected $json;
        if (!$metadata->hasAssociation('json')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('json', 'text')->length(16777215)->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="extra_data", type="text", length=16777215, nullable=true)
//         */
//        protected $extraData;
        if (!$metadata->hasAssociation('extraData')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('extraData', 'text')->length(16777215)->nullable(true)->build();
        }

//        /**
//         * @ORM\Column(name="date", type="datetime", nullable=false)
//         */
//        protected $date;
        if (!$metadata->hasAssociation('date')) {
            $builder = new ClassMetadataBuilder($metadata);
            $builder->createField('date', 'datetime')->nullable(false)->build();
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLog", mappedBy="exception")
//         */
//        protected $log;
        if (!$metadata->hasAssociation('log')) {
            $metadata->mapOneToOne([
                'fieldName'    => 'log',
                'mappedBy'     => 'exception',
                'fetch'        => 'EAGER',
                'targetEntity' => $config['log_class'],
            ]);
        }
    }

}
