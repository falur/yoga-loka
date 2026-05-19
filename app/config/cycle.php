<?php

declare(strict_types=1);


/**
 * Конфигурация Cycle ORM.
 *
 * @link https://spiral.dev/docs/basics-orm#orm
 */
return [
    'schema' => [
        /**
         * true - схема будет сохранена в кэше после компиляции.
         * После изменения entity обновите схему командой `php app.php cycle`.
         *
         * false - схема не будет кэшироваться и будет обновляться автоматически в режиме разработки.
         */
        'cache' => \env('CYCLE_SCHEMA_CACHE', true),

        /**
         * Настройки по умолчанию для сегментов схемы, которые не были заданы явно.
         */
        'defaults' => [
            // SchemaInterface::MAPPER => \Cycle\ORM\Mapper\Mapper::class,
            // SchemaInterface::REPOSITORY => \Cycle\ORM\Select\Repository::class,
            // SchemaInterface::SCOPE => null,
            // SchemaInterface::TYPECAST_HANDLER => [
            //    \Cycle\ORM\Parser\Typecast::class,
            // ],
        ],

        'collections' => [
            'default' => 'illuminate',
            'factories' => ['illuminate' => new Cycle\ORM\Collection\IlluminateCollectionFactory()],
        ],

        /**
         * Генераторы схемы.
         * null - использовать генераторы, заданные bootloader-ами.
         */
        'generators' => null,

        // 'generators' => [
        //        \Cycle\Annotated\Embeddings::class,
        //        \Cycle\Annotated\Entities::class,
        //        \Cycle\Annotated\MergeColumns::class,
        //        \Cycle\Schema\Generator\ResetTables::class,
        //        \Cycle\Schema\Generator\GenerateRelations::class,
        //        \Cycle\Schema\Generator\ValidateEntities::class,
        //        \Cycle\Schema\Generator\RenderTables::class,
        //        \Cycle\Schema\Generator\RenderRelations::class,
        //        \Cycle\Annotated\TableInheritance::class,
        //        \Cycle\Annotated\MergeIndexes::class
        //        \Cycle\Schema\Generator\GenerateTypecast::class,
        // ],
    ],

    'warmup' => \env('RR_MODE') === null ? false : \env('CYCLE_SCHEMA_WARMUP', false),

    /**
     * Пользовательские типы связей для entity.
     */
    'customRelations' => [
        // \Cycle\ORM\Relation::EMBEDDED => [
        //     \Cycle\ORM\Config\RelationConfig::LOADER => \Cycle\ORM\Select\Loader\EmbeddedLoader::class,
        //     \Cycle\ORM\Config\RelationConfig::RELATION => \Cycle\ORM\Relation\Embedded::class,
        // ]
    ],
];
