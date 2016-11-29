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
 * A TranslatableSubscriber ripoff (from DoctrineBehaviours)
 * A copy of the base file can be found in this project and used as a reference
 * Class MappingSubscriber
 * @package Wucdbm\Bundle\WucdbmHttpLoggerBundle\Subscriber
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
            $this->mapRequestLogMessageType($classMetadata);
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

                if ($platform instanceof Platforms\PostgreSqlPlatform) {
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

        $builder = new ClassMetadataBuilder($metadata);

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
            $builder->createField('statusCode', 'smallint')->nullable(true)->option('unsigned', true)->build();
        }

//        /**
//         * @ORM\Column(name="url", type="text", nullable=true)
//         */
//        protected $url;
        if (!$metadata->hasAssociation('url')) {
            $builder->createField('url', 'text')->length(65535)->nullable(true)->build();
        }

//        /**
//         * @ORM\Column(name="url_hash", type="string", nullable=true)
//         */
//        protected $urlHash;
        if (!$metadata->hasAssociation('urlHash')) {
            $builder->createField('urlHash', 'string')->length(32)->option('fixed', true)->nullable(true)->build();
            $builder->addIndex(['urlHash'], 'urlHash');
        }

//        /**
//         * @ORM\Column(name="method", type="string", nullable=true)
//         */
//        protected $method;
        if (!$metadata->hasAssociation('method')) {
            $builder->createField('method', 'string')->nullable(true)->build();
        }

//        /**
//         * @ORM\Column(name="date", type="datetime", nullable=false)
//         */
//        protected $date;
        if (!$metadata->hasAssociation('date')) {
            $builder->createField('date', 'datetime')->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="message", type="string", nullable=false)
//         */
//        protected $message;
        if (!$metadata->hasAssociation('message')) {
            $builder->createField('message', 'string')->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="debug", type="string", nullable=true)
//         */
//        protected $debug;
        if (!$metadata->hasAssociation('debug')) {
            $builder->createField('debug', 'string')->nullable(true)->build();
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\BookingLogMessage", mappedBy="requestTo")
//         */
//        protected $request;
        if (!$metadata->hasAssociation('request')) {
            $metadata->mapOneToOne([
                'fieldName'    => 'request',
                'mappedBy'     => 'requestTo',
                'cascade'      => ['persist', 'merge', 'remove'],
                'fetch'        => 'EAGER',
                'targetEntity' => $config['log_message_class']
            ]);
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\BookingLogMessage", mappedBy="responseTo")
//         */
//        protected $response;
        if (!$metadata->hasAssociation('response')) {
            $metadata->mapOneToOne([
                'fieldName'    => 'response',
                'mappedBy'     => 'responseTo',
                'cascade'      => ['persist', 'merge', 'remove'],
                'fetch'        => 'EAGER',
                'targetEntity' => $config['log_message_class']
            ]);
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLogException", mappedBy="log", orphanRemoval=true)
//         */
//        protected $exception;
        if (!$metadata->hasAssociation('exception')) {
            $metadata->mapOneToOne([
                'fieldName'    => 'exception',
                'mappedBy'     => 'log',
                'fetch'        => 'EAGER',
                'targetEntity' => $config['log_exception_class']
            ]);
        }
    }

    protected function mapRequestLogMessage(ClassMetadata $metadata, EntityManager $em) {
        $reflection = $metadata->getReflectionClass();
        $config = $this->getConfigForClass($reflection->getName());

        $metadata->setPrimaryTable($this->getRequestLogMessageTable($config));

        $builder = new ClassMetadataBuilder($metadata);

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
//         * @ORM\Column(name="headers", type="array", nullable=false)
//         */
//        protected $headers;
        if (!$metadata->hasAssociation('headers')) {
            $builder->createField('headers', 'array')->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="content", type="text", length=16777215, nullable=false)
//         */
//        protected $content;
        if (!$metadata->hasAssociation('content')) {
            $builder->createField('content', 'text')->length(16777215)->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="date", type="datetime", nullable=false)
//         */
//        protected $date;
        if (!$metadata->hasAssociation('date')) {
            $builder->createField('date', 'datetime')->nullable(false)->build();
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLog", inversedBy="request", orphanRemoval=true)
//         * @ORM\JoinColumn(name="request_id", referencedColumnName="id", onDelete="CASCADE", nullable=true)
//         */
//        protected $requestTo;
        if (!$metadata->hasAssociation('requestTo')) {
            $metadata->mapOneToOne([
                'fieldName'    => 'requestTo',
                'inversedBy'   => 'request',
                'cascade'      => ['persist', 'merge'],
                'fetch'        => 'EAGER',
                'joinColumns'  => [[
                    'name'                 => 'request_to_id',
                    'referencedColumnName' => 'id',
                    'onDelete'             => 'CASCADE',
                    'nullable'             => true
                ]],
                'targetEntity' => $config['log_class']
            ]);
        }

//        /**
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLog", inversedBy="response", orphanRemoval=true)
//         * @ORM\JoinColumn(name="response_id", referencedColumnName="id", onDelete="CASCADE", nullable=true)
//         */
//        protected $responseTo;
        if (!$metadata->hasAssociation('responseTo')) {
            $metadata->mapOneToOne([
                'fieldName'    => 'responseTo',
                'inversedBy'   => 'response',
                'cascade'      => ['persist', 'merge'],
                'fetch'        => 'EAGER',
                'joinColumns'  => [[
                    'name'                 => 'response_to_id',
                    'referencedColumnName' => 'id',
                    'onDelete'             => 'CASCADE',
                    'nullable'             => true
                ]],
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
    }

    protected function mapRequestLogMessageType(ClassMetadata $metadata) {
        $reflection = $metadata->getReflectionClass();
        $config = $this->getConfigForClass($reflection->getName());

        $metadata->setPrimaryTable($this->getRequestLogMessageTypeTable($config));

        $builder = new ClassMetadataBuilder($metadata);

//        /**
//         * @ORM\Id
//         * @ORM\Column(name="id", type="integer", options={"unsigned"=true})
//         * @ORM\GeneratedValue(strategy="AUTO")
//         */
//        protected $id;
        if (!$metadata->hasAssociation('id')) {
            $builder->createField('id', 'integer')->makePrimaryKey()->option('unsigned', true)->build();
//            $this->mapId($metadata, $em);
        }

//        /**
//         * @ORM\Column(name="name", type="string", nullable=false)
//         */
//        protected $name;
        if (!$metadata->hasAssociation('name')) {
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

        $builder = new ClassMetadataBuilder($metadata);

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
//         * @ORM\OneToOne(targetEntity="Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLog", inversedBy="log", orphanRemoval=true)
//         * @ORM\JoinColumn(name="log_id", referencedColumnName="id", onDelete="CASCADE", nullable=true)
//         */
//        protected $log;
        if (!$metadata->hasAssociation('log')) {
            $metadata->mapOneToOne([
                'fieldName'     => 'log',
                'inversedBy'    => 'exception',
                'orphanRemoval' => true,
                'cascade'       => ['persist', 'merge'],
                'fetch'         => 'EAGER',
                'joinColumns'   => [[
                    'name'                 => 'log_id',
                    'referencedColumnName' => 'id',
                    'onDelete'             => 'CASCADE',
                    'nullable'             => true
                ]],
                'targetEntity'  => $config['log_class']
            ]);
        }

//        /**
//         * @ORM\Column(name="message", type="text", nullable=true)
//         */
//        protected $message;

        if (!$metadata->hasAssociation('message')) {
            $builder->createField('message', 'text')->nullable(true)->build();
        }

//        /**
//         * @ORM\Column(name="code", type="string", nullable=true)
//         */
//        protected $code;
        if (!$metadata->hasAssociation('code')) {
            $builder->createField('code', 'string')->nullable(true)->build();
        }
//
//        /**
//         * @ORM\Column(name="file", type="text", nullable=false)
//         */
//        protected $file;
        if (!$metadata->hasAssociation('file')) {
            $builder->createField('file', 'text')->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="line", type="integer", options={"unsigned"=true}, nullable=false)
//         */
//        protected $line;
        if (!$metadata->hasAssociation('line')) {
            $builder->createField('line', 'integer')->nullable(false)->option('unsigned', true)->build();
        }

//        /**
//         * @ORM\Column(name="stack_trace_string", type="text", length=16777215, nullable=false)
//         */
//        protected $stackTraceString;
        if (!$metadata->hasAssociation('stackTraceString')) {
            $builder->createField('stackTraceString', 'text')->length(16777215)->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="json", type="text", length=16777215, nullable=false)
//         */
//        protected $json;
        if (!$metadata->hasAssociation('json')) {
            $builder->createField('json', 'text')->length(16777215)->nullable(false)->build();
        }

//        /**
//         * @ORM\Column(name="extra_data", type="text", length=16777215, nullable=true)
//         */
//        protected $extraData;
        if (!$metadata->hasAssociation('extraData')) {
            $builder->createField('extraData', 'text')->length(16777215)->nullable(true)->build();
        }

//        /**
//         * @ORM\Column(name="date", type="datetime", nullable=false)
//         */
//        protected $date;
        if (!$metadata->hasAssociation('date')) {
            $builder->createField('date', 'datetime')->nullable(false)->build();
        }
    }

}
